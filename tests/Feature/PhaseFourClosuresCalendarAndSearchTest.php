<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\FacilityClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhaseFourClosuresCalendarAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $category = Category::create(['name' => 'Training halls']);
        $this->facility = Facility::create(['category_id' => $category->id, 'name' => 'Hall A', 'is_active' => true]);
    }

    private function closurePayload(array $overrides = []): array
    {
        return array_merge([
            'facility_id' => $this->facility->id,
            'starts_at' => '2026-11-09T09:15',
            'ends_at' => '2026-11-09T11:45',
            'reason' => 'Air conditioning maintenance',
        ], $overrides);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'facility_id' => $this->facility->id, 'user_id' => $this->staff->id,
            'start_date' => '2026-11-09', 'end_date' => '2026-11-09',
            'start_time' => '09:30', 'end_time' => '10:30', 'status' => 'approved',
        ], $overrides));
    }

    public function test_only_admin_can_manage_closures_and_bad_intervals_are_rejected(): void
    {
        $this->actingAs($this->staff)->get(route('admin.facility-closures.index'))->assertForbidden();
        $this->post(route('admin.facility-closures.store'), $this->closurePayload())->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.facility-closures.index'))->assertOk();
        $this->post(route('admin.facility-closures.store'), $this->closurePayload(['ends_at' => '2026-11-09T09:00']))
            ->assertSessionHasErrors('ends_at');
        $this->assertDatabaseCount('facility_closures', 0);
    }

    public function test_maintenance_requires_acknowledgement_and_does_not_cancel_existing_bookings(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->admin)->post(route('admin.facility-closures.store'), $this->closurePayload())
            ->assertSessionHasErrors('confirm_affected');
        $this->assertDatabaseCount('facility_closures', 0);
        $this->post(route('admin.facility-closures.store'), $this->closurePayload(['confirm_affected' => '1']))
            ->assertRedirect(route('admin.facility-closures.index'));
        $this->assertDatabaseCount('facility_closures', 1);
        $this->assertSame('approved', $booking->fresh()->status);
    }

    public function test_closed_time_is_blocked_for_staff_and_admin_booking_and_does_not_create_history(): void
    {
        $this->actingAs($this->admin)->post(route('admin.facility-closures.store'), $this->closurePayload())->assertRedirect();
        $payload = [
            'facility_id' => $this->facility->id, 'start_date' => '2026-11-09', 'end_date' => '2026-11-09',
            'start_time' => '09:00', 'end_time' => '10:00', 'purpose' => 'Training',
        ];
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $payload)->assertSessionHasErrors('conflict');
        $this->actingAs($this->admin)->post(route('admin.bookings.store'), $payload + ['user_id' => $this->staff->id])
            ->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_events', 0);
    }

    public function test_calendar_is_thirty_minute_and_duration_aware_with_closure_markers(): void
    {
        $this->actingAs($this->admin)->post(route('admin.facility-closures.store'), $this->closurePayload())->assertRedirect();
        $url = route('calendar.index', ['week' => '2026-11-09', 'facility' => $this->facility->id, 'duration' => 120, 'view' => 'slots']);
        $this->actingAs($this->staff)->get($url)->assertOk()
            ->assertSee('<tr><th scope="row">06:00', false)
            ->assertSee('<tr><th scope="row">06:30', false)
            ->assertSeeInOrder(['<tr><th scope="row">23:30', '<tr><th scope="row">00:00'], false)
            ->assertSee('Closed')->assertSee('Busy');
    }

    public function test_closure_prevents_reschedule_request(): void
    {
        $booking = $this->booking(['start_time' => '13:00', 'end_time' => '14:00']);
        $this->actingAs($this->admin)->post(route('admin.facility-closures.store'), $this->closurePayload())->assertRedirect();
        $this->actingAs($this->staff)->post(route('staff.bookings.reschedule.store', $booking), [
            'start_date' => '2026-11-09', 'end_date' => '2026-11-09',
            'start_time' => '09:30', 'end_time' => '10:30',
        ])->assertSessionHasErrors('conflict');
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertDatabaseCount('booking_reschedule_requests', 0);
    }

    public function test_reschedule_approval_rechecks_for_closures_added_after_request(): void
    {
        $booking = $this->booking(['start_time' => '13:00', 'end_time' => '14:00']);
        $this->actingAs($this->staff)->post(route('staff.bookings.reschedule.store', $booking), [
            'start_date' => '2026-11-09', 'end_date' => '2026-11-09',
            'start_time' => '09:30', 'end_time' => '10:30',
        ])->assertSessionHasNoErrors();
        $proposal = $booking->rescheduleRequests()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.facility-closures.store'), $this->closurePayload())->assertRedirect();
        $this->put(route('admin.reschedules.decide', $proposal), ['decision' => 'approve'])
            ->assertSessionHasErrors('conflict');
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertSame('13:00', substr($booking->fresh()->start_time, 0, 5));
    }

    public function test_adjacent_reservation_remains_available_after_closure_ends(): void
    {
        $this->actingAs($this->admin)->post(route('admin.facility-closures.store'), $this->closurePayload())->assertRedirect();
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), [
            'facility_id' => $this->facility->id,
            'start_date' => '2026-11-09', 'end_date' => '2026-11-09',
            'start_time' => '11:45', 'end_time' => '12:15', 'purpose' => 'Staff meeting',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_closure_can_be_removed_without_erasing_the_audit_record(): void
    {
        $this->actingAs($this->admin)->post(route('admin.facility-closures.store'), $this->closurePayload())->assertRedirect();
        $closure = FacilityClosure::firstOrFail();
        $this->delete(route('admin.facility-closures.destroy', $closure))->assertRedirect();
        $this->assertSoftDeleted('facility_closures', ['id' => $closure->id]);
    }

    public function test_staff_search_is_private_staff_only_and_limited_to_twelve_results(): void
    {
        foreach (range(1, 16) as $i) User::factory()->create(['role' => 'staff', 'name' => 'Example Colleague '.$i]);
        User::factory()->create(['role' => 'admin', 'name' => 'Example Administrator']);
        $this->actingAs($this->staff)->getJson(route('admin.staff-search', ['q' => 'Example']))->assertForbidden();
        $response = $this->actingAs($this->admin)->getJson(route('admin.staff-search', ['q' => 'Example']))->assertOk();
        $this->assertCount(12, $response->json('results'));
        foreach ($response->json('results') as $person) $this->assertStringNotContainsString('Administrator', $person['name']);
        $this->getJson(route('admin.staff-search', ['q' => 'x']))->assertUnprocessable();
    }
}
