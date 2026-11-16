<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeOneBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0, 0));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff', 'name' => 'Nur Aina']);
        $category = Category::create(['name' => 'Rooms']);
        $this->facility = Facility::create(['category_id' => $category->id, 'name' => 'Studio Lab', 'is_active' => true]);
    }

    private function booking(string $date, string $start = '09:00', string $status = 'pending'): Booking
    {
        return Booking::create([
            'user_id' => $this->staff->id,
            'facility_id' => $this->facility->id,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $start,
            'end_time' => '11:00',
            'purpose' => 'Staff collaboration',
            'status' => $status,
        ]);
    }

    public function test_pending_urgent_and_reschedule_queues_share_one_paginated_page(): void
    {
        $soon = $this->booking('2026-11-03', '07:00'); // 23 hours ahead
        $this->booking('2026-11-03', '10:00'); // 26 hours ahead
        $this->booking('2026-11-03', '11:00', 'approved');
        $parent = $this->booking('2026-11-05');
        BookingRescheduleRequest::create([
            'booking_id' => $parent->id, 'user_id' => $this->staff->id,
            'start_date' => '2026-11-06', 'end_date' => '2026-11-06',
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->get(route('admin.bookings.manage'))
            ->assertOk()->assertSee('Booking Management')
            ->assertSee('Pending (3)')->assertSee('Urgent (1)')
            ->assertSee('Reschedules (1)');

        $this->get(route('admin.bookings.manage', ['tab' => 'urgent']))
            ->assertOk()->assertSee('#'.$soon->id.' · Studio Lab')
            ->assertDontSee('#'.$parent->id.' · Studio Lab');

        $this->get(route('admin.bookings.manage', ['tab' => 'reschedules']))
            ->assertOk()->assertSee('Request #1')
            ->assertSee('Review request');
    }

    public function test_booking_management_uses_booking_history_cards_chips_and_buttons(): void
    {
        $this->booking('2026-11-04');

        $this->actingAs($this->admin)->get(route('admin.bookings.manage'))
            ->assertOk()
            ->assertSee('admin-stat-pills', false)
            ->assertSee('admin-chips', false)
            ->assertSee('admin-card', false)
            ->assertSee('admin-table', false)
            ->assertSee('btn-icon btn-icon-success', false)
            ->assertSee('reject-form', false);
    }

    public function test_search_filters_pending_and_reschedules_without_removing_other_tabs(): void
    {
        $booking = $this->booking('2026-11-04');
        $this->actingAs($this->admin)
            ->get(route('admin.bookings.manage', ['tab' => 'pending', 'q' => 'Studio Lab']))
            ->assertOk()->assertSee('Staff collaboration');
        $this->get(route('admin.bookings.manage', ['tab' => 'pending', 'q' => 'NoMatchXY']))
            ->assertOk()->assertDontSee('Staff collaboration');

        BookingRescheduleRequest::create([
            'booking_id' => $booking->id, 'user_id' => $this->staff->id,
            'start_date' => '2026-11-06', 'end_date' => '2026-11-06',
            'status' => 'pending',
        ]);
        $this->get(route('admin.bookings.manage', ['tab' => 'reschedules', 'q' => 'Nur Aina']))
            ->assertOk()->assertSee('Review request');
    }

    public function test_legacy_urls_redirect_to_correct_tabs_and_staff_are_forbidden(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.bookings.pending', ['q' => 'Studio']))
            ->assertRedirect(route('admin.bookings.manage', ['tab' => 'pending', 'q' => 'Studio']));
        $this->get(route('admin.reschedules.index'))
            ->assertRedirect(route('admin.bookings.manage', ['tab' => 'reschedules']));
        $this->get(route('admin.attention.index'))
            ->assertRedirect(route('admin.bookings.manage', ['tab' => 'pending']));

        $this->actingAs($this->staff)->get(route('admin.bookings.manage'))->assertForbidden();
        $this->get(route('admin.bookings.pending'))->assertForbidden();
        $this->get(route('admin.reschedules.index'))->assertForbidden();
    }

    public function test_approval_and_rejection_work_from_consolidated_queue(): void
    {
        $approved = $this->booking('2026-11-04');
        $rejected = $this->booking('2026-11-05');

        $this->actingAs($this->admin)
            ->put(route('admin.bookings.approve', $approved))->assertRedirect();
        $this->put(route('admin.bookings.reject', $rejected), [
            'admin_remarks' => 'Facility requested for another purpose',
        ])->assertRedirect();

        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertDatabaseHas('booking_events', ['booking_id' => $approved->id, 'new_status' => 'approved']);
        $this->assertDatabaseHas('booking_events', ['booking_id' => $rejected->id, 'new_status' => 'rejected']);
    }

    public function test_pagination_keeps_active_tab_and_search_query(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->booking('2026-11-04');
        }
        $this->actingAs($this->admin)->get(route('admin.bookings.manage', ['tab' => 'pending', 'q' => 'Studio', 'page' => 2]))
            ->assertOk()->assertSee('Booking Management')
            ->assertSee('tab=pending', false)->assertSee('page=1', false);
    }
}
