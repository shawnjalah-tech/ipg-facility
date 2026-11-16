<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseOneBookingExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $admin;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0, 0));
        Notification::fake();
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Meeting rooms']);
        $this->facility = Facility::create(['name' => 'Room A', 'category_id' => $category->id, 'is_active' => true]);
    }

    private function futureBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->staff->id, 'facility_id' => $this->facility->id,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'approved',
        ], $attributes));
    }

    public function test_time_picker_order_for_staff_and_admin(): void
    {
        $fragments = ['value="06:00"', 'value="23:45"', 'value="00:00"', 'value="05:45"'];
        $this->actingAs($this->staff)->get(route('staff.bookings.create'))
            ->assertOk()->assertSeeInOrder($fragments, false)->assertSee('Enter exact minute');
        $this->actingAs($this->admin)->get(route('admin.bookings.create'))
            ->assertOk()->assertSeeInOrder($fragments, false)->assertSee('Enter exact minute');
    }

    public function test_rescheduling_uses_the_same_six_am_first_picker_and_preserves_odd_minutes(): void
    {
        $booking = $this->futureBooking(['start_time' => '09:07', 'end_time' => '10:12']);
        $this->actingAs($this->staff)->get(route('staff.bookings.reschedule.create', $booking))
            ->assertOk()->assertSee('value="09:07" selected', false)
            ->assertSee('value="10:12" selected', false)
            ->assertSeeInOrder(['value="06:00"', 'value="23:45"', 'value="00:00"'], false);
    }

    public function test_calendar_shows_early_morning_times_below_evening_and_keeps_slot_links(): void
    {
        $this->actingAs($this->staff)->get('/calendar?view=slots&week=2026-11-09')
            ->assertOk()
            ->assertSeeInOrder(['<tr><th scope="row">06:00', '<tr><th scope="row">23:00', '<tr><th scope="row">00:00', '<tr><th scope="row">05:00'], false)
            ->assertSee('start_time=00%3A00', false);
    }

    public function test_admin_cannot_normally_cancel_an_already_started_booking(): void
    {
        $booking = $this->futureBooking(['start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'start_time' => '07:00']);
        $this->actingAs($this->admin)->put(route('admin.bookings.cancel', $booking), [
            'admin_remarks' => 'Request from the dean',
        ])->assertSessionHasErrors('status');
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(0, $booking->events()->count());
        // Explicit historical correction is still allowed and audited.
        $this->put(route('admin.bookings.update-status', $booking), [
            'expected_status' => 'approved', 'status' => 'cancelled',
            'admin_remarks' => 'Historical correction requested by the dean',
        ])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(1, $booking->events()->count());
    }

    public function test_deactivating_facility_with_approved_bookings_requires_acknowledgement(): void
    {
        $booking = $this->futureBooking();
        $url = route('admin.facilities.update', $this->facility);
        $payload = [
            'category_id' => $this->facility->category_id, 'name' => $this->facility->name,
            'is_active' => '0',
        ];
        $this->actingAs($this->admin)->get(route('admin.facilities.edit', $this->facility))
            ->assertOk()->assertSee('1 approved booking(s)');
        $this->put($url, $payload)->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $this->facility->fresh()->is_active);
        $this->put($url, $payload + ['confirm_deactivation' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $this->facility->fresh()->is_active);
        $this->assertSame('approved', $booking->fresh()->status);
    }

    public function test_deactivation_without_approved_bookings_does_not_require_acknowledgement(): void
    {
        $this->actingAs($this->admin)->put(route('admin.facilities.update', $this->facility), [
            'category_id' => $this->facility->category_id,
            'name' => $this->facility->name,
            'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $this->facility->fresh()->is_active);
    }

    public function test_booking_service_rejects_only_one_time_supplied_even_without_controller_validation(): void
    {
        $this->expectException(ValidationException::class);
        app(BookingService::class)->create($this->staff, [
            'facility_id' => $this->facility->id,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
            'start_time' => '09:00', 'end_time' => null,
        ]);
    }
}
