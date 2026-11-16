<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\FacilityClosure;
use App\Models\User;
use App\Services\FacilityAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseSixAnalyticsAndReleaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 08:00:00'));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $category = Category::create(['name' => 'Training']);
        $this->facility = Facility::create(['name' => 'Conference Hall', 'category_id' => $category->id, 'is_active' => true]);
    }

    private function booking(array $data = []): Booking
    {
        return Booking::create(array_merge([
            'facility_id' => $this->facility->id,
            'user_id' => $this->staff->id,
            'start_date' => '2026-11-19',
            'end_date' => '2026-11-19',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'approved',
            'purpose' => 'Analytics sample',
        ], $data));
    }

    public function test_analytics_is_private_to_admin_and_staff_cannot_export(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));
        $this->actingAs($this->staff)->get(route('admin.analytics.index'))->assertForbidden();
        $this->get(route('admin.analytics.export'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.analytics.index', ['month' => '2026-11']))
            ->assertOk()->assertSee('Facility Analytics')->assertSee('Conference Hall');
    }

    public function test_invalid_months_are_rejected(): void
    {
        $this->actingAs($this->admin)->get(route('admin.analytics.index', ['month' => 'wrong']))
            ->assertSessionHasErrors('month');
        $this->get(route('admin.analytics.export', ['month' => '2026-14']))
            ->assertSessionHasErrors('month');
    }

    public function test_clips_overnight_booking_to_selected_month(): void
    {
        $this->booking([
            'start_date' => '2026-10-31', 'end_date' => '2026-11-01',
            'start_time' => '23:00', 'end_time' => '01:00',
        ]);
        $data = app(FacilityAnalyticsService::class)->forMonth(Carbon::parse('2026-11-01'));
        $this->assertEquals(1.0, $data['facilityRows'][0]['booked_hours']);
        $this->assertSame(1, $data['statusCounts']['approved']);
    }

    public function test_overlapping_historical_approved_intervals_are_not_double_counted(): void
    {
        $this->booking(['start_time' => '09:00', 'end_time' => '11:00']);
        $this->booking(['start_time' => '10:00', 'end_time' => '12:00']);
        $data = app(FacilityAnalyticsService::class)->forMonth(Carbon::parse('2026-11-01'));
        $this->assertEquals(3.0, $data['facilityRows'][0]['booked_hours']);
        $this->assertSame(2, $data['facilityRows'][0]['approved_count']);
        $this->assertEquals(720, $data['facilityRows'][0]['calendar_hours']);
    }

    public function test_all_day_booking_uses_full_calendar_day_and_ignores_pending_hours(): void
    {
        $this->booking(['start_time' => null, 'end_time' => null]);
        $this->booking(['start_time' => '08:00', 'end_time' => '09:00', 'status' => 'pending']);
        $data = app(FacilityAnalyticsService::class)->forMonth(Carbon::parse('2026-11-01'));
        $this->assertEquals(24.0, $data['facilityRows'][0]['booked_hours']);
        $this->assertSame(1, $data['statusCounts']['pending']);
    }

    public function test_closure_time_is_separate_and_deleted_closures_are_excluded(): void
    {
        $one = FacilityClosure::create([
            'facility_id' => $this->facility->id, 'created_by' => $this->admin->id,
            'starts_at' => '2026-11-19 10:00', 'ends_at' => '2026-11-19 12:00', 'reason' => 'Repair',
        ]);
        FacilityClosure::create([
            'facility_id' => $this->facility->id, 'created_by' => $this->admin->id,
            'starts_at' => '2026-11-19 11:00', 'ends_at' => '2026-11-19 13:00', 'reason' => 'Setup',
        ]);
        $data = app(FacilityAnalyticsService::class)->forMonth(Carbon::parse('2026-11-01'));
        $this->assertEquals(3.0, $data['facilityRows'][0]['closed_hours']);
        $one->delete();
        $data = app(FacilityAnalyticsService::class)->forMonth(Carbon::parse('2026-11-01'));
        $this->assertEquals(2.0, $data['facilityRows'][0]['closed_hours']);
    }

    public function test_decision_turnaround_uses_audited_events_and_creation_trend(): void
    {
        $booking = $this->booking(['status' => 'approved']);
        // Fixture timestamps are historical data, not application saves: use
        // raw updates to avoid Eloquent automatically managing timestamps.
        DB::table('bookings')->where('id', $booking->id)
            ->update(['created_at' => '2026-11-10 08:00:00']);
        $event = $booking->events()->create([
            'user_id' => $this->admin->id, 'actor_name' => 'Admin',
            'old_status' => 'pending', 'new_status' => 'approved',
            'created_at' => '2026-11-10 12:00:00', 'updated_at' => '2026-11-10 12:00:00',
        ]);
        // Fail with a meaningful timestamp assertion before testing analytics
        // if the fixture is ever rewritten by model events or configuration.
        $this->assertSame('2026-11-10 08:00:00',
            DB::table('bookings')->where('id', $booking->id)->value('created_at'));
        $this->assertSame('2026-11-10 12:00:00',
            DB::table('booking_events')->where('id', $event->id)->value('created_at'));
        $data = app(FacilityAnalyticsService::class)->forMonth(Carbon::parse('2026-11-01'));
        $this->assertEquals(4.0, $data['avgDecisionHours']);
        $this->assertSame(1, $data['turnaroundCount']);
        $this->assertSame(1, $data['decisions']['approved']);
        $this->assertSame(1, $data['dailyRequests'][10]);
    }

    public function test_csv_streams_metrics_and_prevents_formula_injection(): void
    {
        $this->facility->update(['name' => '=DANGEROUS()']);
        $this->booking();
        $response = $this->actingAs($this->admin)->get(route('admin.analytics.export', ['month' => '2026-11']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=DANGEROUS()", $csv);
        $this->assertStringContainsString('Approved bookings', $csv);
        $this->assertStringContainsString('ipg-facility-utilization-2026-11.csv', $response->headers->get('content-disposition'));
    }

    public function test_an_empty_month_returns_zero_totals_without_division_errors(): void
    {
        $data = app(FacilityAnalyticsService::class)->forMonth(Carbon::parse('2028-01-01'));
        $this->assertSame(0, $data['scheduledTotal']);
        $this->assertNull($data['avgDecisionHours']);
        $this->assertSame(0, $data['requestsCreated']);
        $this->assertEquals(0, $data['facilityRows'][0]['utilization_percent']);
    }
}
