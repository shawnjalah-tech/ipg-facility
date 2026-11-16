<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_links_keep_search_and_reset_pagination(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Rooms']);
        $facility = Facility::create(['name' => 'Meeting Room', 'category_id' => $category->id, 'is_active' => true]);
        Booking::create([
            'user_id' => $staff->id, 'facility_id' => $facility->id,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'approved',
        ]);
        foreach ([['/staff/my-bookings', $staff, 'approved', 'status'], ['/admin/bookings', $admin, 'approved', 'status'], ['/admin/users', $admin, 'staff', 'role']] as [$url, $user, $value, $filter]) {
            $response = $this->actingAs($user)->get($url.'?q=Meeting&page=2')->assertOk();
            $response->assertSee($url.'?q=Meeting&amp;'.$filter.'='.$value, false);
            $response->assertSee('aria-current="page"', false);
        }
    }

    public function test_weekly_board_shares_permitted_details_but_keeps_booking_management_private(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        $staff = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Rooms']);
        $facility = Facility::create(['name' => 'Meeting Room', 'category_id' => $category->id, 'is_active' => true]);
        $booking = Booking::create([
            'user_id' => $other->id, 'facility_id' => $facility->id,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'approved',
            'purpose' => 'Publicly visible meeting purpose',
            'admin_remarks' => 'PRIVATE APPROVAL COMMENT',
        ]);
        $url = '/calendar?week=2026-11-02&facility='.$facility->id;
        $dayUrl = route('calendar.day', ['facility' => $facility->id, 'date' => '2026-11-03']);

        // The default weekly board navigates to a selected booking's day details for both roles.
        foreach ([$admin, $staff] as $viewer) {
            $this->actingAs($viewer)->get($url)->assertOk()
                ->assertSee('href="'.e($dayUrl).'#fb-booking-'.$booking->id.'"', false)
                ->assertDontSee('href="'.route('bookings.show', $booking).'"', false)
                ->assertDontSee('PRIVATE APPROVAL COMMENT');
            $this->get($dayUrl)->assertOk()
                ->assertSee('Publicly visible meeting purpose')
                ->assertDontSee('PRIVATE APPROVAL COMMENT');
        }

        // Admins retain direct booking links in the preserved detailed slot grid and day panel.
        $this->actingAs($admin)->get($url.'&view=slots')->assertOk()
            ->assertSee('href="'.route('bookings.show', $booking).'"', false);
        $this->get($dayUrl)->assertOk()
            ->assertSee('href="'.route('bookings.show', $booking).'"', false);

        // Staff may see the purpose but cannot access another user's management page.
        $this->actingAs($staff)->get($dayUrl)->assertOk()
            ->assertDontSee('href="'.route('bookings.show', $booking).'"', false);
        $this->get(route('bookings.show', $booking))->assertForbidden();
    }
}
