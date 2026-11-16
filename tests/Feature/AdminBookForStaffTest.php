<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminBookForStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $otherStaff;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->otherStaff = User::factory()->create(['role' => 'staff']);
        $category = Category::create(['name' => 'Training Rooms']);
        $this->facility = Facility::create([
            'category_id' => $category->id,
            'name' => 'Training Room A',
            'capacity' => 40,
            'is_active' => true,
        ]);
    }

    private function payload(array $changes = []): array
    {
        return array_merge([
            'user_id' => $this->staff->id,
            'facility_id' => $this->facility->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-05',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'purpose' => 'Staff meeting',
        ], $changes);
    }

    public function test_admin_can_book_for_staff_and_automatically_approve(): void
    {
        $this->actingAs($this->admin)->get(route('admin.bookings.create'))
            ->assertOk()->assertSee('Book a Facility for Staff')
            ->assertSee($this->staff->email);

        $response = $this->post(route('admin.bookings.store'), $this->payload());
        $booking = Booking::firstOrFail();

        $response->assertRedirect(route('bookings.show', $booking));
        $this->assertSame('approved', $booking->status);
        $this->assertSame($this->staff->id, $booking->user_id);
        $this->assertSame('Staff meeting', $booking->purpose);
        $this->assertSame($this->admin->id, $booking->events()->firstOrFail()->user_id);
        $this->assertSame($this->admin->name, $booking->events()->firstOrFail()->actor_name);
        $this->assertStringContainsString('on behalf of', $booking->events()->firstOrFail()->notes);
        Notification::assertSentTo($this->staff, BookingStatusChanged::class);

        $this->actingAs($this->staff)->get(route('staff.my-bookings'))
            ->assertOk()->assertSee('Training Room A');
        $this->actingAs($this->otherStaff)->get(route('staff.my-bookings'))
            ->assertOk()->assertDontSee('Training Room A');
    }

    public function test_staff_cannot_access_admin_booking_routes(): void
    {
        $this->actingAs($this->staff)->get(route('admin.bookings.create'))->assertForbidden();
        $this->post(route('admin.bookings.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_cannot_assign_an_admin_or_unknown_user(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.store'), $this->payload(['user_id' => $this->admin->id]))
            ->assertSessionHasErrors('user_id');
        $this->post(route('admin.bookings.store'), $this->payload(['user_id' => 999999]))
            ->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_conflicts_are_rejected_without_partial_booking_or_audit_event(): void
    {
        Booking::create([
            'user_id' => $this->otherStaff->id,
            'facility_id' => $this->facility->id,
            'start_date' => '2026-11-05', 'end_date' => '2026-11-05',
            'start_time' => '10:30', 'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->post(route('admin.bookings.store'), $this->payload())
            ->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_events', 0);
    }

    public function test_inactive_facilities_and_invalid_dates_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.bookings.store'), $this->payload([
            'start_date' => '2026-11-01', 'end_date' => '2026-11-01',
        ]))->assertSessionHasErrors('start_date');

        $this->facility->update(['is_active' => false]);
        $this->post(route('admin.bookings.store'), $this->payload())
            ->assertSessionHasErrors('facility_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_calendar_can_link_to_prefilled_booking_form(): void
    {
        $this->actingAs($this->admin)->get(route('calendar.index', [
            'week' => '2026-11-02', 'facility' => $this->facility->id,
        ]))->assertOk()->assertSee('/admin/bookings/create?', false);

        $this->get(route('admin.bookings.create', [
            'facility_id' => $this->facility->id,
            'start_date' => '2026-11-05', 'end_date' => '2026-11-05',
            'start_time' => '13:00', 'end_time' => '14:00',
        ]))->assertOk()->assertSee('value="13:00"', false);
    }
}
