<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class FacilitySystemTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        Notification::fake();
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Rooms']);
        $this->facility = Facility::create(['category_id' => $category->id, 'name' => 'Meeting Room', 'is_active' => true, 'capacity' => 20]);
    }

    private function data(array $extra = []): array
    {
        return array_merge(['facility_id' => $this->facility->id, 'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'start_time' => '09:00', 'end_time' => '10:00', 'purpose' => 'Meeting'], $extra);
    }

    private function booking(array $extra = []): Booking
    {
        return Booking::create(array_merge($this->data(), ['user_id' => $this->staff->id, 'status' => 'pending'], $extra));
    }

    public function test_guest_and_staff_access_boundaries(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($this->staff)->get('/admin/users')->assertForbidden();
        $this->get('/admin/bookings/export')->assertForbidden();
    }

    public function test_every_core_page_renders(): void
    {
        $b = $this->booking();
        $this->actingAs($this->admin);
        foreach (['/dashboard', '/calendar', '/admin/categories', '/admin/categories/create', '/admin/categories/'.$this->facility->category_id.'/edit', '/admin/facilities', '/admin/facilities/create', '/admin/facilities/'.$this->facility->id.'/edit', '/admin/users', '/admin/users/create', '/admin/users/'.$this->staff->id.'/edit', '/admin/bookings', '/admin/bookings/manage', '/bookings/'.$b->id] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/bookings/pending')
            ->assertRedirect(route('admin.bookings.manage', ['tab' => 'pending']));
        $this->actingAs($this->staff);
        foreach (['/dashboard', '/staff/bookings/create', '/staff/my-bookings', '/calendar', '/bookings/'.$b->id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_auth_pages_render(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/reset-password/token?email=hello@example.com'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_create_approve_and_cancel_records_history_and_notification(): void
    {
        $this->actingAs($this->staff)->post('/staff/bookings', $this->data())->assertSessionHasNoErrors()->assertRedirect('/staff/my-bookings');
        $b = Booking::firstOrFail();
        $this->assertSame('pending', $b->status);
        $this->assertSame(1, $b->events()->count());
        $this->actingAs($this->admin)->put('/admin/bookings/'.$b->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', $b->fresh()->status);
        $this->actingAs($this->staff)->put('/staff/bookings/'.$b->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame(3, $b->events()->count());
        Notification::assertSentTo($this->staff, BookingStatusChanged::class);
    }

    public function test_time_overlap_blocked_but_adjacent_slots_allowed(): void
    {
        $this->booking();
        $this->actingAs($this->staff)->post('/staff/bookings', $this->data(['start_time' => '09:30', 'end_time' => '10:30']))->assertSessionHasErrors('conflict');
        $this->post('/staff/bookings', $this->data(['start_time' => '10:00', 'end_time' => '11:00']))->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::count());
    }

    public function test_all_day_booking_blocks_timed_booking(): void
    {
        $this->booking(['start_time' => null, 'end_time' => null]);
        $this->actingAs($this->staff)->post('/staff/bookings', $this->data())->assertSessionHasErrors('conflict');
    }

    public function test_overnight_booking_and_multiday_containment(): void
    {
        $this->actingAs($this->staff)->post('/staff/bookings', $this->data(['start_time' => '23:00', 'end_date' => '2026-11-04', 'end_time' => '02:00']))->assertSessionHasNoErrors();
        $this->post('/staff/bookings', $this->data(['start_date' => '2026-11-04', 'end_date' => '2026-11-04', 'start_time' => '01:00', 'end_time' => '03:00']))->assertSessionHasErrors('conflict');
    }

    public function test_inactive_facility_and_bad_dates_are_rejected(): void
    {
        $this->actingAs($this->staff);
        $this->post('/staff/bookings', $this->data(['end_time' => null]))->assertSessionHasErrors('end_time');
        $this->post('/staff/bookings', $this->data(['start_time' => '10:00', 'end_time' => '09:00']))->assertSessionHasErrors('end_time');
        $this->post('/staff/bookings', $this->data(['end_date' => '2026-12-03']))->assertSessionHasErrors('end_date');
        $this->post('/staff/bookings', $this->data(['start_date' => '2026-11-01']))->assertSessionHasErrors('start_date');
        $this->facility->update(['is_active' => false]);
        $this->post('/staff/bookings', $this->data())->assertSessionHasErrors('facility_id');
        $this->assertSame(0, Booking::count());
    }

    public function test_same_day_future_slot_can_be_cancelled(): void
    {
        $b = $this->booking(['start_date' => '2026-11-02', 'end_date' => '2026-11-02']);
        $this->actingAs($this->staff)->put('/staff/bookings/'.$b->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $b->fresh()->status);
    }

    public function test_started_booking_cannot_be_cancelled_by_staff(): void
    {
        $b = $this->booking(['start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'start_time' => '07:00']);
        $this->actingAs($this->staff)->put('/staff/bookings/'.$b->id.'/cancel')->assertSessionHasErrors('status');
    }

    public function test_ownership_enforced_for_details_and_cancel(): void
    {
        $b = $this->booking();
        $other = User::factory()->create(['role' => 'staff']);
        $this->actingAs($other)->get('/bookings/'.$b->id)->assertForbidden();
        $this->put('/staff/bookings/'.$b->id.'/cancel')->assertForbidden();
    }

    public function test_rejection_requires_reason_and_cannot_be_reapproved(): void
    {
        $b = $this->booking();
        $this->actingAs($this->admin);
        $this->put('/admin/bookings/'.$b->id.'/reject')->assertSessionHasErrors('admin_remarks');
        $this->put('/admin/bookings/'.$b->id.'/reject', ['admin_remarks' => 'Maintenance scheduled'])->assertSessionHasNoErrors();
        $this->put('/admin/bookings/'.$b->id.'/approve')->assertSessionHasErrors('status');
        $this->assertSame('rejected', $b->fresh()->status);
    }

    public function test_approval_rechecks_conflict_and_facility_status(): void
    {
        $this->booking(['status' => 'approved']);
        $b = $this->booking();
        $this->actingAs($this->admin)->put('/admin/bookings/'.$b->id.'/approve')->assertSessionHasErrors('conflict');
        $this->facility->update(['is_active' => false]);
        $this->put('/admin/bookings/'.$b->id.'/approve')->assertSessionHasErrors('status');
    }

    public function test_calendar_displays_both_timed_bookings(): void
    {
        $one = $this->booking(['status' => 'approved']);
        $two = $this->booking(['status' => 'approved', 'start_time' => '11:00', 'end_time' => '12:00']);
        // The weekly board intentionally shows names; the original ID-labelled links remain in Detailed slots.
        $this->actingAs($this->admin)->get('/calendar?week=2026-11-02')->assertOk()
            ->assertSee($this->staff->name)
            ->assertSee('09:00–10:00')->assertSee('11:00–12:00');
        $this->get('/calendar?view=slots&week=2026-11-02')->assertOk()
            ->assertSee('#'.$one->id)->assertSee('#'.$two->id)->assertSee('09:00')->assertSee('11:00');
        $this->get('/calendar?week=garbage')->assertSessionHasErrors('week');
    }

    public function test_search_pagination_and_csv_export(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->booking(['purpose' => $i === 0 ? '=SUM(1,2)' : 'Training '.$i]);
        }
        $this->actingAs($this->admin)->get('/admin/bookings')->assertOk()->assertSee('page=2');
        $this->get('/admin/bookings?q=SUM')->assertOk()->assertSee('SUM(1,2)')->assertDontSee('Training 1');
        $csv = $this->get('/admin/bookings/export?q=SUM')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringNotContainsString('Training 1', $csv);
    }

    public function test_existing_history_prevents_destructive_deletion(): void
    {
        $this->booking(['status' => 'cancelled']);
        $this->actingAs($this->admin);
        $this->delete('/admin/facilities/'.$this->facility->id)->assertSessionHas('error');
        $this->delete('/admin/users/'.$this->staff->id)->assertSessionHas('error');
        $this->delete('/admin/categories/'.$this->facility->category_id)->assertSessionHas('error');
        $this->assertSame(1, Booking::count());
    }

    public function test_password_reset_token_flow(): void
    {
        $this->post('/forgot-password', ['email' => $this->staff->email])->assertSessionHas('status');
        Notification::assertSentTo($this->staff, ResetPassword::class);
        $token = Password::createToken($this->staff);
        $data = ['token' => $token, 'email' => $this->staff->email, 'password' => 'A-new-password-123', 'password_confirmation' => 'A-new-password-123'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('A-new-password-123', $this->staff->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_login_registration_and_throttling(): void
    {
        $this->post('/register', ['name' => 'New Staff', 'email' => 'new@ipg.test', 'employee_id' => 'N001', 'password' => 'a-password-123', 'password_confirmation' => 'a-password-123', 'role' => 'admin'])->assertRedirect('/dashboard');
        $this->assertSame('staff', User::where('email', 'new@ipg.test')->firstOrFail()->role);
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => 'new@ipg.test', 'password' => 'a-password-123'])->assertRedirect('/dashboard');
        $this->post('/logout');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'nobody@ipg.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'nobody@ipg.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_admin_can_manage_categories_facilities_and_users(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/categories', ['name' => 'Labs'])->assertSessionHasNoErrors();
        $category = Category::where('name', 'Labs')->firstOrFail();
        $this->post('/admin/facilities', ['category_id' => $category->id, 'name' => 'Science Lab', 'capacity' => 30, 'is_active' => 1])->assertSessionHasNoErrors();
        $facility = Facility::where('name', 'Science Lab')->firstOrFail();
        $this->put('/admin/facilities/'.$facility->id, ['category_id' => $category->id, 'name' => 'Science Lab Updated', 'capacity' => 25])->assertSessionHasNoErrors();
        $this->assertFalse($facility->fresh()->is_active);
        $this->post('/admin/users', ['name' => 'Managed User', 'email' => 'managed@ipg.test', 'role' => 'staff', 'password' => 'secure-password', 'password_confirmation' => 'secure-password'])->assertSessionHasNoErrors();
        $user = User::where('email', 'managed@ipg.test')->firstOrFail();
        $this->put('/admin/users/'.$user->id, ['name' => 'Updated User', 'email' => $user->email, 'role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('admin', $user->fresh()->role);
        $this->delete('/admin/users/'.$user->id)->assertSessionHas('status');
        $this->delete('/admin/facilities/'.$facility->id)->assertSessionHas('status');
        $this->delete('/admin/categories/'.$category->id)->assertSessionHas('status');
        $this->assertNull($facility->fresh());
        $this->assertNull($user->fresh());
    }

    public function test_admin_cannot_delete_self_or_demote_self(): void
    {
        $this->actingAs($this->admin)->delete('/admin/users/'.$this->admin->id)->assertSessionHas('error');
        $this->put('/admin/users/'.$this->admin->id, ['name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'staff'])->assertSessionHas('error');
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_database_blocks_cascade_deletion_of_booking_history(): void
    {
        $this->booking(['status' => 'cancelled']);
        $this->expectException(QueryException::class);
        $this->facility->delete();
    }

    public function test_csv_export_supports_single_ended_date_filter(): void
    {
        $this->booking();
        $this->actingAs($this->admin)->get('/admin/bookings/export?to=2026-11-03')->assertOk();
        $this->get('/admin/bookings/export?from=2026-11-04&to=2026-11-03')->assertSessionHasErrors('to');
    }
}
