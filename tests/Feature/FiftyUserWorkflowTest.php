<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FiftyUserWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifty_accounts_complete_mixed_booking_and_browsing_workflows(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        Notification::fake();
        $bookingStaff = User::factory()->count(25)->create(['role' => 'staff']);
        $browsingStaff = User::factory()->count(20)->create(['role' => 'staff']);
        $admins = User::factory()->count(5)->create(['role' => 'admin']);
        $category = Category::create(['name' => '50-user workflow']);
        $facility = Facility::create(['name' => 'Shared Hall', 'category_id' => $category->id, 'is_active' => true]);
        $bookings = [];

        foreach ($bookingStaff as $index => $staff) {
            $date = today()->addDays($index + 1)->toDateString();
            $this->actingAs($staff)->post(route('staff.bookings.store'), [
                'facility_id' => $facility->id, 'start_date' => $date, 'end_date' => $date,
                'start_time' => '09:00', 'end_time' => '10:00', 'purpose' => 'Workflow '.$index,
            ])->assertSessionHasNoErrors()->assertRedirect(route('staff.my-bookings'));
            $booking = Booking::where('user_id', $staff->id)->sole();
            $bookings[] = $booking;
            $admin = $admins[intdiv($index, 5)];
            $kind = $index % 5;
            if (in_array($kind, [0, 3], true)) {
                $this->actingAs($admin)->put(route('admin.bookings.approve', $booking))->assertSessionHasNoErrors();
            } elseif ($kind === 1) {
                $this->actingAs($admin)->put(route('admin.bookings.reject', $booking), ['admin_remarks' => 'Reserved for maintenance'])->assertSessionHasNoErrors();
            }
            if (in_array($kind, [2, 3], true)) {
                $this->actingAs($staff)->put(route('staff.bookings.cancel', $booking))->assertSessionHasNoErrors();
            }
            $expected = match ($kind) {
                0 => 'approved', 1 => 'rejected', 2, 3 => 'cancelled', default => 'pending',
            };
            $this->assertSame($expected, $booking->fresh()->status);
            $this->actingAs($staff)->get(route('bookings.show', $booking))->assertOk()->assertSee('Workflow '.$index);
        }

        foreach ($browsingStaff as $staff) {
            $this->actingAs($staff)->get('/dashboard')->assertOk();
            $this->get('/calendar?facility='.$facility->id)->assertOk();
            $this->get('/staff/my-bookings')->assertOk()->assertDontSee('Workflow 0');
            $this->get('/bookings/'.$bookings[0]->id)->assertForbidden();
            $this->put('/staff/bookings/'.$bookings[0]->id.'/cancel')->assertForbidden();
            $this->get('/admin/bookings')->assertForbidden();
        }
        foreach ($admins as $admin) {
            $this->actingAs($admin)->get('/admin/bookings')->assertOk();
            $this->get('/admin/bookings/manage?tab=pending')->assertOk();
            $this->get('/admin/bookings/pending')
                ->assertRedirect(route('admin.bookings.manage', ['tab' => 'pending']));
            $this->get('/admin/reports/monthly?month=2026-11')->assertOk();
            $this->get('/admin/bookings/export')->assertOk();
        }
        $this->assertSame(50, User::count());
        $this->assertSame(25, Booking::count());
        $this->assertSame(5, Booking::where('status', 'approved')->count());
        $this->assertSame(5, Booking::where('status', 'rejected')->count());
        $this->assertSame(10, Booking::where('status', 'cancelled')->count());
        $this->assertSame(5, Booking::where('status', 'pending')->count());
        $this->assertSame(50, BookingEvent::count());
        Notification::assertSentToTimes($bookingStaff[0], BookingStatusChanged::class, 1);
        Notification::assertSentToTimes($bookingStaff[3], BookingStatusChanged::class, 2);
        Notification::assertNotSentTo($bookingStaff[4], BookingStatusChanged::class);
    }

    public function test_stale_admin_decision_does_not_overwrite_a_newer_decision(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $admins = User::factory()->count(2)->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Rooms']);
        $facility = Facility::create(['name' => 'Room', 'category_id' => $category->id, 'is_active' => true]);
        $booking = app(BookingService::class)->create($staff, [
            'facility_id' => $facility->id, 'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
            'start_time' => '09:00', 'end_time' => '10:00',
        ]);
        $this->actingAs($admins[0])->putJson(route('admin.bookings.approve', $booking))->assertRedirect();
        $this->actingAs($admins[1])->putJson(route('admin.bookings.update-status', $booking), [
            'expected_status' => 'pending', 'status' => 'rejected', 'admin_remarks' => 'Stale decision must fail',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(2, $booking->events()->count());
        Notification::assertSentToTimes($staff, BookingStatusChanged::class, 1);
    }

    public function test_thirty_login_attempt_ip_limit_applies_across_distinct_accounts(): void
    {
        for ($index = 0; $index < 30; $index++) {
            $this->post('/login', ['email' => 'unknown'.$index.'@example.test', 'password' => 'invalid'])
                ->assertRedirect();
        }
        $this->postJson('/login', ['email' => 'another@example.test', 'password' => 'invalid'])
            ->assertStatus(429);
    }
}
