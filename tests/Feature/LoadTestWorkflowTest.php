<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LoadTestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $booker;
    private User $admin;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->booker = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->facility = Facility::create([
            'category_id' => Category::create(['name' => 'Test halls'])->id,
            'name' => 'Smoke Test Hall', 'is_active' => true,
        ]);
    }

    private function requestBooking(int $shift = 5): Booking
    {
        $day = today()->addDays($shift)->toDateString();
        $this->actingAs($this->booker)->post(route('staff.bookings.store'), [
            'facility_id' => $this->facility->id, 'start_date' => $day, 'end_date' => $day,
            'start_time' => '09:00', 'end_time' => '10:00', 'purpose' => 'Load test smoke booking',
        ])->assertSessionHasNoErrors();
        return Booking::latest('id')->firstOrFail();
    }

    public function test_booking_approval_writes_auditable_history(): void
    {
        $booking = $this->requestBooking();
        $this->assertSame('pending', $booking->status);
        $this->actingAs($this->admin)->put(route('admin.bookings.approve', $booking))
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(['pending', 'approved'], $booking->events()->pluck('new_status')->all());
    }

    public function test_rejection_and_admin_cancellation_are_logged(): void
    {
        $rejected = $this->requestBooking();
        $this->actingAs($this->admin)->put(route('admin.bookings.reject', $rejected), [
            'admin_remarks' => 'Meeting cancelled by management',
        ])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $rejected->fresh()->status);
        $cancelled = $this->requestBooking(10);
        $this->actingAs($this->admin)
            ->put(route('admin.bookings.cancel', $cancelled), [
                'admin_remarks' => 'Facility closed for maintenance',
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertDatabaseHas('booking_events', ['booking_id' => $cancelled->id, 'new_status' => 'cancelled']);
    }

    public function test_staff_cannot_use_admin_approval_route(): void
    {
        $booking = $this->requestBooking();
        $this->actingAs($this->booker)->put(route('admin.bookings.approve', $booking))->assertForbidden();
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_overlapping_booking_is_rejected_and_not_inserted(): void
    {
        $this->requestBooking();
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->post(route('staff.bookings.store'), [
                'facility_id' => $this->facility->id,
                'start_date' => today()->addDays(5)->toDateString(),
                'end_date' => today()->addDays(5)->toDateString(),
                'start_time' => '09:30', 'end_time' => '10:30', 'purpose' => 'Collision',
            ])->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_pending_booking_expires_after_scheduled_start(): void
    {
        $booking = $this->requestBooking(2);
        $this->travel(3)->days();
        $this->assertSame(1, app(BookingService::class)->expireOverduePending());
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame(0, app(BookingService::class)->expireOverduePending());
    }

    public function test_staff_can_request_a_reschedule_and_admin_can_accept(): void
    {
        $booking = $this->requestBooking();
        $newDate = today()->addDays(7)->toDateString();
        $this->actingAs($this->booker)->post(route('staff.bookings.reschedule.store', $booking), [
            'start_date' => $newDate, 'end_date' => $newDate,
            'start_time' => '14:00', 'end_time' => '15:00',
        ])->assertSessionHasNoErrors();
        $this->assertNotSame($newDate, $booking->fresh()->start_date->toDateString());
        $proposal = $booking->rescheduleRequests()->firstOrFail();
        $this->actingAs($this->admin)->put(route('admin.reschedules.decide', $proposal), [
            'decision' => 'approve', 'review_notes' => 'Suitable new time',
        ])->assertSessionHasNoErrors();
        $this->assertSame($newDate, $booking->fresh()->start_date->toDateString());
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_reschedule_requests', ['id' => $proposal->id, 'status' => 'approved']);
    }

    public function test_calendar_and_test_lab_access_permissions(): void
    {
        $this->actingAs($this->booker)->get(route('calendar.index'))->assertOk();
        $this->get(route('admin.load-test.index'))->assertForbidden();
    }

    public function test_staff_can_cancel_own_pending_booking_but_not_someone_elses(): void
    {
        $booking = $this->requestBooking();
        $stranger = User::factory()->create(['role' => 'staff']);
        $this->actingAs($stranger)->put(route('staff.bookings.cancel', $booking))->assertForbidden();
        $this->actingAs($this->booker)->put(route('staff.bookings.cancel', $booking))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_admin_can_correct_rejection_with_reason_and_history(): void
    {
        $booking = $this->requestBooking();
        $this->actingAs($this->admin)->put(route('admin.bookings.reject', $booking), [
            'admin_remarks' => 'Original scheduling conflict',
        ])->assertSessionHasNoErrors();
        $this->put(route('admin.bookings.update-status', $booking), [
            'status' => 'approved', 'expected_status' => 'rejected',
            'admin_remarks' => 'Verified the space is now available',
        ])->assertSessionHasNoErrors();
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(3, $booking->events()->count());
    }

    public function test_safety_handshake_is_hidden_outside_loadtest_environment(): void
    {
        $this->get('/__loadtest/ready')->assertNotFound();
    }

}
