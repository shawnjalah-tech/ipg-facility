<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\FacilityClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSixTwoCalendarVisualTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $colleague;
    private Facility $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->colleague = User::factory()->create(['role' => 'staff', 'name' => 'Example Colleague']);
        $category = Category::create(['name' => 'Meeting rooms']);
        $this->room = Facility::create(['category_id' => $category->id, 'name' => 'Meeting A', 'is_active' => true]);
    }

    private function booking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->colleague->id,
            'facility_id' => $this->room->id,
            'start_date' => '2026-11-04', 'end_date' => '2026-11-04',
            'start_time' => '09:00', 'end_time' => '10:00',
            'purpose' => 'Teaching meeting', 'admin_remarks' => 'PRIVATE REMARK',
            'status' => 'approved',
        ], $attributes));
    }

    public function test_booker_card_is_the_only_explicit_details_link_for_staff_and_admin(): void
    {
        $booking = $this->booking();
        $dateUrl = route('calendar.day', ['facility' => $this->room->id, 'date' => '2026-11-04']);
        foreach ([$this->staff, $this->admin] as $user) {
            $response = $this->actingAs($user)->get('/calendar?week=2026-11-02');
            $response->assertOk()->assertSee('Example Colleague')
                ->assertSee('href="'.e($dateUrl).'#fb-booking-'.$booking->id.'"', false)
                ->assertSee('data-day-detail data-booking-id="'.$booking->id.'"', false)
                ->assertDontSee('class="fb-detail-link"', false)
                ->assertDontSee('PRIVATE REMARK');
        }
        $this->actingAs($this->staff)->get($dateUrl)->assertOk()
            ->assertSee('id="fb-booking-'.$booking->id.'"', false)
            ->assertSee('Teaching meeting')->assertDontSee('PRIVATE REMARK');
        $this->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($this->admin)->get($dateUrl)->assertOk()
            ->assertSee('View booking #'.$booking->id);
    }

    public function test_free_days_use_the_full_cell_book_action_and_keep_booking_prefill(): void
    {
        $response = $this->actingAs($this->staff)->get('/calendar?week=2026-11-02');
        $response->assertOk()->assertSee('fb-is-free', false)->assertSee('fb-cell-content', false)
            ->assertSee('class="fb-book"', false)->assertSee('+ Book');
        $prefilled = route('staff.bookings.create', [
            'facility_id' => $this->room->id,
            'start_date' => '2026-11-06', 'end_date' => '2026-11-06',
        ]);
        $response->assertSee('href="'.e($prefilled).'"', false);
        $this->get('/calendar?view=slots&week=2026-11-02')->assertOk()->assertSee('06:30');
    }

    public function test_partial_closure_and_existing_booking_still_allow_booking_remaining_hours(): void
    {
        $this->booking();
        FacilityClosure::create([
            'facility_id' => $this->room->id,
            'created_by' => $this->admin->id,
            'starts_at' => '2026-11-05 12:00:00',
            'ends_at' => '2026-11-05 13:00:00',
            'reason' => 'Routine cleaning', 'notes' => 'PRIVATE MAINTENANCE NOTE',
        ]);
        $response = $this->actingAs($this->staff)->get('/calendar?week=2026-11-02');
        $response->assertOk()->assertSee('fb-is-limited', false)
            ->assertSee('Limited hours')->assertSee('Example Colleague')->assertSee('+ Book')
            ->assertDontSee('PRIVATE MAINTENANCE NOTE');
        $this->get(route('calendar.day', ['facility' => $this->room->id, 'date' => '2026-11-05']))
            ->assertOk()->assertSee('Routine cleaning')->assertDontSee('PRIVATE MAINTENANCE NOTE');
    }
}
