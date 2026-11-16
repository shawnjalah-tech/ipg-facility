<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\FacilityClosure;
use App\Models\RecurringBookingSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhaseFiveRecurringBookingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $other;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->other = User::factory()->create(['role' => 'staff']);
        $category = Category::create(['name' => 'Labs']);
        $this->facility = Facility::create(['category_id' => $category->id, 'name' => 'Test Lab', 'is_active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'facility_id' => $this->facility->id,
            'start_date' => '2026-11-09', 'end_date' => '2026-11-09',
            'start_time' => '09:00', 'end_time' => '10:00',
            'purpose' => 'Semester lessons', 'repeat' => 'weekly', 'repeat_count' => 4,
        ], $overrides);
    }

    private function closure(string $date): void
    {
        FacilityClosure::create([
            'facility_id' => $this->facility->id,
            'created_by' => $this->admin->id,
            'starts_at' => $date.' 09:00:00',
            'ends_at' => $date.' 10:00:00',
            'reason' => 'Maintenance',
        ]);
    }

    public function test_weekly_request_creates_linked_pending_occurrences_and_audit_events(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();
        $series = RecurringBookingSeries::firstOrFail();
        $bookings = $series->bookings;
        $this->assertSame(['2026-11-09', '2026-11-16', '2026-11-23', '2026-11-30'],
            $bookings->map(fn ($b) => $b->start_date->toDateString())->all());
        $this->assertSame(4, $bookings->count());
        $this->assertTrue($bookings->every(fn ($b) => $b->status === 'pending' && $b->user_id === $this->staff->id));
        $this->assertDatabaseCount('booking_events', 4);
        $this->actingAs($this->staff)->get(route('recurring.show', $series))->assertOk()->assertSee('Semester lessons')->assertSee('Cancel this date');
    }

    public function test_conflict_on_later_week_undoes_whole_series_and_audit(): void
    {
        Booking::create(array_merge($this->payload(['repeat' => 'none']), [
            'user_id' => $this->other->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23',
            'status' => 'approved',
        ]));
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload())
            ->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('recurring_booking_series', 0);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_events', 0);
    }

    public function test_maintenance_on_later_week_undoes_whole_series(): void
    {
        $this->closure('2026-11-23');
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload())
            ->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('recurring_booking_series', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_on_behalf_creates_approved_series_owned_by_staff(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bookings.store'), $this->payload([
            'user_id' => $this->staff->id,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $series = RecurringBookingSeries::firstOrFail();
        $this->assertSame($this->admin->id, $series->created_by);
        $this->assertTrue($series->bookings->every(fn ($b) => $b->status === 'approved' && $b->user_id === $this->staff->id));
        $this->assertSame($this->admin->id, $series->bookings->first()->events->first()->user_id);
    }

    public function test_bulk_approval_is_atomic_when_a_closure_appears_after_request(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload())->assertRedirect();
        $series = RecurringBookingSeries::firstOrFail();
        $this->closure('2026-11-23');
        $this->actingAs($this->admin)->post(route('admin.recurring.decide', $series), ['decision' => 'approved'])
            ->assertSessionHasErrors('conflict');
        $this->assertSame(0, $series->bookings()->where('status', 'approved')->count());
        $this->assertSame(4, $series->bookings()->where('status', 'pending')->count());
    }

    public function test_admin_can_approve_all_pending_occurrences_with_audit(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload())->assertRedirect();
        $series = RecurringBookingSeries::firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.recurring.decide', $series), ['decision' => 'approved'])
            ->assertRedirect(route('recurring.show', $series));
        $this->assertSame(4, $series->bookings()->where('status', 'approved')->count());
        $this->assertDatabaseCount('booking_events', 8);
        $this->post(route('admin.recurring.decide', $series), ['decision' => 'approved'])
            ->assertSessionHasErrors('decision');
    }

    public function test_admin_can_reject_pending_series_with_reason(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload())->assertRedirect();
        $series = RecurringBookingSeries::firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.recurring.decide', $series), [
            'decision' => 'rejected', 'reason' => 'Venue not available this semester',
        ])->assertRedirect();
        $this->assertSame(4, $series->bookings()->where('status', 'rejected')->count());
    }

    public function test_monthly_recurrence_preserves_original_day_at_end_of_month(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload([
            'start_date' => '2027-01-31', 'end_date' => '2027-01-31',
            'repeat' => 'monthly', 'repeat_count' => 3,
        ]))->assertRedirect();
        $series = RecurringBookingSeries::firstOrFail();
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'],
            $series->bookings->map(fn ($b) => $b->start_date->toDateString())->all());
    }

    public function test_invalid_repeat_count_and_admin_owner_are_rejected(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload([
            'repeat' => 'monthly', 'repeat_count' => 13,
        ]))->assertSessionHasErrors('repeat_count');
        $this->actingAs($this->admin)->post(route('admin.bookings.store'), $this->payload([
            'user_id' => $this->admin->id,
        ]))->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_series_is_private_and_occurrences_can_be_cancelled_individually(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload())->assertRedirect();
        $series = RecurringBookingSeries::firstOrFail();
        $this->actingAs($this->other)->get(route('recurring.show', $series))->assertForbidden();
        $this->post(route('admin.recurring.decide', $series), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->staff)->put(route('staff.bookings.cancel', $series->bookings->first()))->assertRedirect();
        $this->assertSame(1, $series->bookings()->where('status', 'cancelled')->count());
        $this->assertSame(3, $series->bookings()->where('status', 'pending')->count());
    }

    public function test_regular_single_booking_is_unchanged(): void
    {
        $this->actingAs($this->staff)->post(route('staff.bookings.store'), $this->payload([
            'repeat' => 'none', 'repeat_count' => null,
        ]))->assertRedirect(route('staff.my-bookings'));
        $this->assertDatabaseCount('recurring_booking_series', 0);
        $this->assertDatabaseCount('bookings', 1);
    }
}
