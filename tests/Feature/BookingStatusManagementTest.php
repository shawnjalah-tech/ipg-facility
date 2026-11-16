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

class BookingStatusManagementTest extends TestCase
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
        $category = Category::create(['name' => 'Hall']);
        $this->facility = Facility::create(['category_id' => $category->id, 'name' => 'Assembly Hall', 'capacity' => 100, 'is_active' => true]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->staff->id, 'facility_id' => $this->facility->id,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'pending',
            'purpose' => 'School activity',
        ], $overrides));
    }

    private function revise(Booking $booking, string $status, ?string $previous = null, string $reason = 'Decision corrected after review')
    {
        return $this->put(route('admin.bookings.update-status', $booking), [
            'status' => $status, 'expected_status' => $previous ?? $booking->fresh()->status,
            'admin_remarks' => $reason,
        ]);
    }

    public function test_admin_can_change_a_completed_decision_and_history_is_retained(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->admin)->put(route('admin.bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->get(route('admin.bookings.edit-status', $booking))->assertOk()->assertSee('Edit booking #'.$booking->id);
        $this->revise($booking, 'rejected')->assertSessionHasNoErrors();
        $this->assertSame('rejected', $booking->fresh()->status);
        $this->assertSame('Administrative status correction: Decision corrected after review', $booking->events()->latest('id')->first()->notes);
        $this->assertSame('approved', $booking->events()->latest('id')->first()->old_status);
        $this->revise($booking, 'cancelled')->assertSessionHasNoErrors();
        $this->revise($booking, 'approved')->assertSessionHasNoErrors();
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(4, $booking->events()->count());
        Notification::assertSentTo($this->staff, BookingStatusChanged::class);
    }

    public function test_admin_can_change_rejected_or_cancelled_to_another_valid_status(): void
    {
        $rejected = $this->booking(['status' => 'rejected']);
        $cancelled = $this->booking(['status' => 'cancelled', 'start_time' => '11:00', 'end_time' => '12:00']);
        $this->actingAs($this->admin);
        $this->revise($rejected, 'pending')->assertSessionHasNoErrors();
        $this->revise($cancelled, 'approved')->assertSessionHasNoErrors();
        $this->assertSame('pending', $rejected->fresh()->status);
        $this->assertSame('approved', $cancelled->fresh()->status);
    }

    public function test_edit_is_admin_only_and_requires_reason_and_fresh_status(): void
    {
        $booking = $this->booking(['status' => 'rejected']);
        $this->actingAs($this->staff)->get(route('admin.bookings.edit-status', $booking))->assertForbidden();
        $this->revise($booking, 'cancelled')->assertForbidden();
        $this->actingAs($this->admin);
        $this->revise($booking, 'approved', 'pending')->assertSessionHasErrors('status');
        $this->revise($booking, 'approved', 'rejected', 'No')->assertSessionHasErrors('admin_remarks');
        $this->revise($booking, 'rejected')->assertSessionHasErrors('status');
        $this->assertSame('rejected', $booking->fresh()->status);
        $this->assertSame(0, $booking->events()->count());
    }

    public function test_correction_cannot_double_book_or_restore_past_booking(): void
    {
        $this->booking(['status' => 'approved']);
        $candidate = $this->booking(['status' => 'rejected']);
        $this->actingAs($this->admin)->revise($candidate, 'approved')->assertSessionHasErrors('conflict');
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(9, 1));
        $this->revise($candidate, 'pending')->assertSessionHasErrors('status');
        $this->revise($candidate, 'approved')->assertSessionHasErrors('status');
        $this->assertSame('rejected', $candidate->fresh()->status);
    }

    public function test_expiration_command_updates_only_overdue_pending_bookings(): void
    {
        $overdue = $this->booking(['start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'start_time' => '07:59', 'end_time' => '10:00']);
        $allDay = $this->booking(['start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'start_time' => null, 'end_time' => null]);
        $future = $this->booking(['start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'start_time' => '09:00', 'end_time' => '10:00']);
        $approved = $this->booking(['status' => 'approved', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01']);
        $rejected = $this->booking(['status' => 'rejected', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01']);
        $this->artisan('bookings:expire')->assertExitCode(0);
        $this->assertSame('expired', $overdue->fresh()->status);
        $this->assertSame('expired', $allDay->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('System', $overdue->events()->first()->actor_name);
        $this->assertNull($overdue->events()->first()->user_id);
        $this->assertSame('pending', $overdue->events()->first()->old_status);
        $this->assertSame('expired', $overdue->events()->first()->new_status);
        $this->artisan('bookings:expire')->assertExitCode(0);
        $this->assertSame(1, $overdue->events()->count());
        $this->assertSame(1, $allDay->events()->count());
    }

    public function test_expired_status_is_visible_in_booking_history_and_exports(): void
    {
        $booking = $this->booking(['status' => 'expired']);
        $this->actingAs($this->admin)->get('/admin/bookings?status=expired')->assertOk()->assertSee('#'.$booking->id);
        $this->get('/admin/bookings/export?status=expired')->assertOk();
        $this->actingAs($this->staff)->get('/staff/my-bookings?status=expired')->assertOk()->assertSee('Expired');
    }
}
