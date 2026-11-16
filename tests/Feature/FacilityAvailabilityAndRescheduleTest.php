<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Notifications\BookingRescheduleReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FacilityAvailabilityAndRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    private User $other;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        Notification::fake();
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->other = User::factory()->create(['role' => 'staff']);
        $category = Category::create(['name' => 'Hall']);
        $this->facility = Facility::create([
            'category_id' => $category->id, 'name' => 'Multipurpose Hall',
            'capacity' => 80, 'is_active' => true,
        ]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'facility_id' => $this->facility->id, 'user_id' => $this->staff->id,
            'start_date' => '2026-11-04', 'end_date' => '2026-11-04',
            'start_time' => '10:00', 'end_time' => '11:00',
            'status' => 'approved', 'purpose' => 'Meeting',
        ], $overrides));
    }

    private function requestChange(Booking $booking, array $overrides = [])
    {
        return $this->post(route('staff.bookings.reschedule.store', $booking), array_merge([
            'start_date' => '2026-11-05', 'end_date' => '2026-11-05',
            'start_time' => '14:00', 'end_time' => '15:00',
        ], $overrides));
    }

    private function decide(BookingRescheduleRequest $proposal, string $decision = 'approve', ?string $notes = null)
    {
        return $this->put(route('admin.reschedules.decide', $proposal), [
            'decision' => $decision, 'review_notes' => $notes,
        ]);
    }

    public function test_hourly_calendar_shows_pending_approved_and_free_slots(): void
    {
        $this->booking(['start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'start_time' => '09:30', 'end_time' => '10:30']);
        $this->booking(['start_date' => '2026-11-04', 'end_date' => '2026-11-04', 'start_time' => null, 'end_time' => null, 'status' => 'pending']);
        $this->actingAs($this->staff)->get(route('calendar.index', [
            'week' => '2026-11-02', 'facility' => $this->facility->id, 'view' => 'slots',
        ]))->assertOk()->assertSee('Facility Availability')
            ->assertSee('Booked')->assertSee('Pending')->assertSee('>Book</a>', false)
            ->assertSee('start_time=11%3A00', false);
    }

    public function test_calendar_links_open_a_prefilled_booking_form(): void
    {
        $url = route('staff.bookings.create', [
            'facility_id' => $this->facility->id,
            'start_date' => '2026-11-05', 'end_date' => '2026-11-05',
            'start_time' => '13:00', 'end_time' => '14:00',
        ]);
        $this->actingAs($this->staff)->get($url)->assertOk()
            ->assertSee('value="2026-11-05"', false)
            ->assertSee('value="13:00"', false)
            ->assertSee('value="14:00"', false);
    }

    public function test_request_keeps_approved_booking_unchanged_until_admin_approves_it(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->staff)->requestChange($booking)->assertSessionHasNoErrors();
        $proposal = BookingRescheduleRequest::firstOrFail();
        $this->assertSame('pending', $proposal->status);
        $this->assertSame('2026-11-04', $booking->fresh()->start_date->toDateString());
        $this->assertSame('approved', $booking->fresh()->status);

        $this->actingAs($this->admin)->get(route('admin.reschedules.index'))
            ->assertRedirect(route('admin.bookings.manage', ['tab' => 'reschedules']));
        $this->get(route('admin.bookings.manage', ['tab' => 'reschedules']))
            ->assertOk()->assertSee('Multipurpose Hall');
        $this->decide($proposal, 'approve', 'Change approved')->assertSessionHasNoErrors();
        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertSame('2026-11-05', $booking->fresh()->start_date->toDateString());
        $this->assertSame('14:00', substr($booking->fresh()->start_time, 0, 5));
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame('approved', $booking->events()->latest('id')->first()->old_status);
        $this->assertStringContainsString('2026-11-04', $booking->events()->latest('id')->first()->notes);
        Notification::assertSentTo($this->staff, BookingRescheduleReviewed::class);
    }

    public function test_pending_booking_becomes_approved_when_amended_request_is_accepted(): void
    {
        $booking = $this->booking(['status' => 'pending']);
        $this->actingAs($this->staff)->requestChange($booking)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->decide(BookingRescheduleRequest::firstOrFail())->assertSessionHasNoErrors();
        $this->assertSame('approved', $booking->fresh()->status);
    }

    public function test_rejecting_or_withdrawing_a_request_does_not_change_the_reservation(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->staff)->requestChange($booking)->assertSessionHasNoErrors();
        $proposal = BookingRescheduleRequest::firstOrFail();
        $this->actingAs($this->admin)->decide($proposal, 'reject', 'Time cannot be changed')->assertSessionHasNoErrors();
        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame('2026-11-04', $booking->fresh()->start_date->toDateString());
        $this->actingAs($this->staff)->requestChange($booking)->assertSessionHasNoErrors();
        $second = BookingRescheduleRequest::latest('id')->firstOrFail();
        $this->delete(route('staff.reschedules.withdraw', $second))->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $second->fresh()->status);
        $this->assertSame('2026-11-04', $booking->fresh()->start_date->toDateString());
    }

    public function test_conflicts_are_prevented_both_when_requested_and_at_approval(): void
    {
        $booking = $this->booking();
        $this->booking(['user_id' => $this->other->id, 'start_date' => '2026-11-05', 'end_date' => '2026-11-05', 'start_time' => '14:30', 'end_time' => '15:30', 'status' => 'pending']);
        $this->actingAs($this->staff)->requestChange($booking)->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('booking_reschedule_requests', 0);
        $this->requestChange($booking, ['start_time' => '16:00', 'end_time' => '17:00'])->assertSessionHasNoErrors();
        $proposal = BookingRescheduleRequest::firstOrFail();
        $this->booking(['user_id' => $this->other->id, 'start_date' => '2026-11-05', 'end_date' => '2026-11-05', 'start_time' => '16:00', 'end_time' => '17:00', 'status' => 'approved']);
        $this->actingAs($this->admin)->decide($proposal)->assertSessionHasErrors('conflict');
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertSame('2026-11-04', $booking->fresh()->start_date->toDateString());
    }

    public function test_ownership_and_admin_review_permissions_are_enforced(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->other)->get(route('staff.bookings.reschedule.create', $booking))->assertForbidden();
        $this->requestChange($booking)->assertForbidden();
        $this->actingAs($this->staff)->requestChange($booking)->assertSessionHasNoErrors();
        $proposal = BookingRescheduleRequest::firstOrFail();
        $this->actingAs($this->staff)->get(route('admin.reschedules.index'))->assertForbidden();
        $this->decide($proposal)->assertForbidden();
        $this->actingAs($this->other)->delete(route('staff.reschedules.withdraw', $proposal))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.reschedules.show', $proposal))->assertOk();
    }

    public function test_duplicate_requests_past_dates_and_no_change_are_rejected(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->staff)->requestChange($booking, [
            'start_date' => '2026-11-04', 'end_date' => '2026-11-04',
            'start_time' => '10:00', 'end_time' => '11:00',
        ])->assertSessionHasErrors('start_date');
        $this->requestChange($booking)->assertSessionHasNoErrors();
        $this->requestChange($booking)->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('booking_reschedule_requests', 1);
    }

    public function test_cancellation_or_expiration_withdraws_pending_requests(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->staff)->requestChange($booking)->assertSessionHasNoErrors();
        $proposal = BookingRescheduleRequest::firstOrFail();
        $this->put(route('staff.bookings.cancel', $booking))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('withdrawn', $proposal->fresh()->status);

        $otherBooking = $this->booking(['status' => 'pending', 'start_date' => '2026-11-03', 'end_date' => '2026-11-03']);
        $this->actingAs($this->staff)->requestChange($otherBooking)->assertSessionHasNoErrors();
        $otherRequest = BookingRescheduleRequest::latest('id')->firstOrFail();
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(10, 1));
        $this->artisan('bookings:expire')->assertExitCode(0);
        $this->assertSame('expired', $otherBooking->fresh()->status);
        $this->assertSame('withdrawn', $otherRequest->fresh()->status);
    }
}
