<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\FacilityClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseSixOneFacilityBoardTest extends TestCase
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
        $this->room = Facility::create(['category_id' => Category::create(['name' => 'Meeting rooms'])->id, 'name' => 'Meeting A', 'is_active' => true]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->colleague->id, 'facility_id' => $this->room->id,
            'start_date' => '2026-11-04', 'end_date' => '2026-11-04',
            'start_time' => '09:00', 'end_time' => '10:00',
            'purpose' => 'Review the syllabus', 'admin_remarks' => 'CONFIDENTIAL DECISION NOTES', 'status' => 'approved',
        ], $overrides));
    }

    public function test_board_is_default_and_categories_expand_with_names_and_book_actions(): void
    {
        $this->booking();
        $response = $this->actingAs($this->staff)->get('/calendar?week=2026-11-02');
        $response->assertOk()->assertSee('WEEKLY OVERVIEW')->assertSee('Meeting rooms')
            ->assertSee('Meeting A')->assertSee('Example Colleague')->assertSee('09:00–10:00')
            ->assertSee('+ Book')->assertSee('Detailed slots');
        $response->assertDontSee('CONFIDENTIAL DECISION NOTES');
        $response->assertDontSee('Review the syllabus'); // purpose is shown only on demand
    }

    public function test_staff_can_view_another_staff_members_purpose_but_not_private_remarks_or_edit_route(): void
    {
        $booking = $this->booking(['purpose' => '<unsafe>Board & strategy']);
        $url = route('calendar.day', ['facility' => $this->room->id, 'date' => '2026-11-04']);
        $this->actingAs($this->staff)->get($url)->assertOk()
            ->assertSee('Example Colleague')->assertSee('&lt;unsafe&gt;Board &amp; strategy', false)
            ->assertDontSee('<unsafe>', false)->assertDontSee('CONFIDENTIAL DECISION NOTES')
            ->assertDontSee(route('bookings.show', $booking));
        $this->get(route('bookings.show', $booking))->assertForbidden();
        $this->get($url.'&fragment=1')->assertOk()->assertDontSee('<!doctype html>', false)
            ->assertDontSee('CONFIDENTIAL DECISION NOTES');
        $this->actingAs($this->admin)->get($url)->assertOk()
            ->assertSee('Example Colleague')->assertSee(route('bookings.show', $booking))
            ->assertDontSee('CONFIDENTIAL DECISION NOTES');
    }

    public function test_closures_show_the_reason_and_do_not_infer_weekend_closure(): void
    {
        FacilityClosure::create([
            'facility_id' => $this->room->id, 'created_by' => $this->admin->id,
            'starts_at' => '2026-11-04 00:00:00', 'ends_at' => '2026-11-05 00:00:00',
            'reason' => 'Projector replacement', 'notes' => 'PRIVATE TECHNICIAN NOTE',
        ]);
        $this->actingAs($this->staff)->get('/calendar?week=2026-11-02')->assertOk()
            ->assertSee('Closed')->assertSee('Book');
        $this->get(route('calendar.day', ['facility' => $this->room->id, 'date' => '2026-11-04']))
            ->assertOk()->assertSee('Projector replacement')->assertDontSee('PRIVATE TECHNICIAN NOTE')
            ->assertDontSee('+ Book this date');
        $this->get(route('calendar.day', ['facility' => $this->room->id, 'date' => '2026-11-07']))
            ->assertOk()->assertSee('+ Book this date');
    }

    public function test_multiple_and_overnight_bookings_are_visible_without_double_booking_claims(): void
    {
        $this->booking(['start_time' => '22:00', 'end_date' => '2026-11-05', 'end_time' => '02:00']);
        $this->booking(['start_time' => '14:00', 'end_time' => '15:00', 'status' => 'pending', 'purpose' => 'Group discussion']);
        $response = $this->actingAs($this->staff)->get('/calendar?week=2026-11-02');
        $response->assertOk()->assertSee('Pending')->assertSee('22:00–00:00');
        $this->get(route('calendar.day', ['facility' => $this->room->id, 'date' => '2026-11-05']))
            ->assertOk()->assertSee('Review the syllabus')->assertSee('02:00');
    }

    public function test_old_detailed_grid_and_reschedule_links_remain_usable(): void
    {
        $booking = $this->booking(['user_id' => $this->staff->id]);
        $this->actingAs($this->staff)->get('/calendar?view=slots&week=2026-11-02')->assertOk()->assertSee('06:30');
        $this->get(route('calendar.index', ['week' => '2026-11-02', 'reschedule' => $booking->id]))
            ->assertOk()->assertSee('Back to rescheduling')->assertSee('reschedule='.$booking->id, false);
        $this->get(route('calendar.day', ['facility' => $this->room->id, 'date' => '2026-11-06', 'reschedule' => $booking->id]))
            ->assertOk()->assertSee(route('staff.bookings.reschedule.create', ['booking' => $booking->id]), false);
    }

    public function test_permissions_and_filters_are_preserved(): void
    {
        $otherRoom = Facility::create(['category_id' => Category::create(['name' => 'Labs'])->id, 'name' => 'Lab A', 'is_active' => false]);
        $this->get('/calendar')->assertRedirect('/login');
        $this->actingAs($this->staff)->get('/calendar?week=bad-date')->assertSessionHasErrors('week');
        $this->get(route('calendar.day', ['facility' => $otherRoom->id, 'date' => '2026-11-04']))->assertNotFound();
        $this->get(route('calendar.index', ['week' => '2026-11-02', 'category' => $this->room->category_id]))
            ->assertOk()->assertSee('Meeting A')->assertDontSee('Lab A');
        $this->get(route('calendar.day', ['facility' => $this->room->id, 'date' => 'not-a-date']))->assertSessionHasErrors('date');
    }

    public function test_board_queries_are_batched_not_one_query_per_facility_or_day(): void
    {
        foreach (range(1, 16) as $n) {
            Facility::create(['category_id' => $this->room->category_id, 'name' => 'Room '.$n, 'is_active' => true]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->staff)->get('/calendar?week=2026-11-02')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(16, $queryCount, 'Rendering the weekly board should batch its queries.');
    }
}
