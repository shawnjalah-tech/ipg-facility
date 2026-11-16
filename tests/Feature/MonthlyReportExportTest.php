<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReportController;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MonthlyReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Rooms']);
        $this->facility = Facility::create(['name' => 'Report Room', 'category_id' => $category->id, 'is_active' => true]);
    }

    private function seedBookings(int $count): void
    {
        $rows = [];
        for ($index = 1; $index <= $count; $index++) {
            $rows[] = [
                'facility_id' => $this->facility->id, 'user_id' => $this->staff->id,
                'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
                'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'cancelled',
                'purpose' => sprintf('Booking marker %04d', $index), 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Booking::insert($chunk);
        }
    }

    private function capturePdf(callable $inspect, int $times = 1): void
    {
        $renderer = Mockery::mock(PdfDocument::class);
        $renderer->shouldReceive('setPaper')->times($times)->with('A4', 'portrait')->andReturnSelf();
        $renderer->shouldReceive('setOptions')->times($times)->andReturnSelf();
        $renderer->shouldReceive('download')->times($times)->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->times($times)->andReturnUsing(function (string $view, array $data) use ($inspect, $renderer) {
            $this->assertSame('admin.reports.pdf.monthly', $view);
            $inspect($data);

            return $renderer;
        });
    }

    public function test_small_pdf_preserves_every_detail_at_the_limit(): void
    {
        $this->seedBookings(ReportController::BOOKINGS_PER_PART);
        $this->capturePdf(function (array $data): void {
            $this->assertFalse($data['summaryOnly']);
            $this->assertSame(50, $data['bookings']->count());
            $this->assertSame('Booking marker 0050', $data['bookings']->last()->purpose);
        });
        $this->actingAs($this->admin)->get('/admin/reports/monthly/download?month=2026-11')->assertOk();
    }

    public function test_one_booking_above_the_limit_switches_to_an_explicit_summary(): void
    {
        $this->seedBookings(ReportController::BOOKINGS_PER_PART + 1);
        $this->capturePdf(function (array $data): void {
            $this->assertTrue($data['summaryOnly']);
            $this->assertSame(51, $data['totalBookings']);
            $this->assertSame(2, $data['pdfParts']);
            $this->assertCount(0, $data['bookings']);
        });
        $this->actingAs($this->admin)->get('/admin/reports/monthly/download?month=2026-11')->assertOk();
    }

    public function test_detail_parts_do_not_omit_or_repeat_records(): void
    {
        $this->seedBookings(51);
        $seen = [];
        $this->capturePdf(function (array $data) use (&$seen): void {
            $this->assertFalse($data['summaryOnly']);
            $this->assertLessThanOrEqual(50, $data['bookings']->count());
            $seen[$data['pdfPart']] = $data['bookings']->pluck('id')->all();
        }, 2);
        $this->actingAs($this->admin)->get('/admin/reports/monthly/download?month=2026-11&part=1')->assertOk();
        $this->get('/admin/reports/monthly/download?month=2026-11&part=2')->assertOk();
        $this->assertCount(50, $seen[1]);
        $this->assertCount(1, $seen[2]);
        $this->assertSame(Booking::orderBy('id')->pluck('id')->all(), array_merge($seen[1], $seen[2]));
    }

    public function test_large_pdf_retains_complete_totals_without_loading_detail_records(): void
    {
        $this->seedBookings(1200);
        $this->capturePdf(function (array $data): void {
            $this->assertTrue($data['summaryOnly']);
            $this->assertSame(1200, $data['totalBookings']);
            $this->assertSame(1200, $data['statusCounts']['cancelled']);
            $this->assertSame(1200, $data['byDayOfWeek']['Tuesday']);
            $this->assertSame(1200, $data['byFacility']->first()['count']);
            $this->assertSame(24, $data['pdfParts']);
            $this->assertCount(0, $data['bookings']);
        });
        $this->actingAs($this->admin)->get('/admin/reports/monthly/download?month=2026-11')->assertOk();
    }

    public function test_report_page_paginates_details_and_exposes_all_export_choices(): void
    {
        $this->seedBookings(1200);
        $response = $this->actingAs($this->admin)->get('/admin/reports/monthly?month=2026-11&page=2')->assertOk()
            ->assertSee('Summary PDF')->assertSee('Monthly CSV')->assertSee('PDF part (of 24)')
            ->assertSee('Booking marker 0051')->assertDontSee('Booking marker 0050');
        $data = $response->viewData('data');
        $this->assertSame(1200, $data['bookings']->total());
        $this->assertCount(50, $data['bookings']);
        $this->assertSame(1200, $data['totalBookings']);
    }

    public function test_summary_counts_cross_month_bookings_and_clips_weekday_totals_to_the_month(): void
    {
        foreach ([['2026-10-31', '2026-11-02'], ['2026-11-30', '2026-12-02'], ['2026-10-30', '2026-10-31']] as [$start, $end]) {
            Booking::create(['facility_id' => $this->facility->id, 'user_id' => $this->staff->id,
                'start_date' => $start, 'end_date' => $end, 'status' => 'approved']);
        }
        $data = $this->actingAs($this->admin)->get('/admin/reports/monthly?month=2026-11')->assertOk()->viewData('data');
        $this->assertSame(2, $data['totalBookings']);
        $this->assertSame(2, $data['statusCounts']['approved']);
        $this->assertSame(1, $data['byDayOfWeek']['Sunday']);
        $this->assertSame(2, $data['byDayOfWeek']['Monday']);
        $this->assertSame(3, array_sum($data['byDayOfWeek']));
    }

    public function test_monthly_csv_keeps_the_entire_large_dataset(): void
    {
        $this->seedBookings(1200);
        $csv = $this->actingAs($this->admin)->get('/admin/bookings/export?from=2026-11-01&to=2026-11-30')->assertOk()->streamedContent();
        $this->assertSame(1201, substr_count($csv, "\n"));
        $this->assertStringContainsString('Booking marker 0001', $csv);
        $this->assertStringContainsString('Booking marker 1200', $csv);
    }

    public function test_a_full_detail_part_renders_a_real_pdf_with_long_fields(): void
    {
        $this->seedBookings(51);
        $this->facility->update(['name' => str_repeat('Room ', 51)]);
        $this->staff->update(['name' => str_repeat('Name ', 51)]);
        Booking::query()->update(['purpose' => str_repeat('purpose ', 62)]);
        $response = $this->actingAs($this->admin)->get('/admin/reports/monthly/download?month=2026-11&part=1')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_invalid_pdf_parts_are_rejected_before_rendering(): void
    {
        $this->seedBookings(51);
        Pdf::shouldReceive('loadView')->never();
        $this->actingAs($this->admin);
        foreach (['0', '-1', '3', '999999999999', 'abc'] as $part) {
            $this->getJson('/admin/reports/monthly/download?month=2026-11&part='.$part)
                ->assertUnprocessable()->assertJsonValidationErrors('part');
        }
    }

    public function test_report_access_remains_admin_only(): void
    {
        $this->get('/admin/reports/monthly/download?month=2026-11')->assertRedirect('/login');
        $this->actingAs($this->staff)->get('/admin/reports/monthly')->assertForbidden();
        $this->get('/admin/reports/monthly/download?month=2026-11&part=1')->assertForbidden();
    }
}
