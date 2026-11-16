<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeAttentionCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $admin;
    private User $other;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0, 0));
        config()->set('attention.admin_email_enabled', false);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->other = User::factory()->create(['role' => 'staff']);
        $category = Category::create(['name' => 'Halls']);
        $this->facility = Facility::create(['name' => 'Seminar Hall', 'category_id' => $category->id, 'is_active' => true]);
    }

    private function schedule(string $date = '2026-11-04', string $start = '09:00'): array
    {
        return [
            'facility_id' => $this->facility->id,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $start,
            'end_time' => '12:00',
            'purpose' => 'Faculty meeting',
        ];
    }

    public function test_staff_request_generates_admin_notice_and_staff_cannot_see_admin_center(): void
    {
        $booking = app(BookingService::class)->create($this->staff, $this->schedule());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->admin->id]);
        $this->assertSame('booking_requested', $this->admin->fresh()->unreadNotifications()->first()->data['kind']);
        $this->actingAs($this->admin)->get(route('admin.attention.index'))
            ->assertRedirect(route('admin.bookings.manage', ['tab' => 'pending']));
        $this->get(route('admin.bookings.manage'))
            ->assertOk()->assertSee('Booking Management')->assertSee('Seminar Hall');
        $this->actingAs($this->staff)->get(route('admin.attention.index'))->assertForbidden();
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_status_change_notifies_owner_and_keeps_admin_history(): void
    {
        $booking = app(BookingService::class)->create($this->staff, $this->schedule());
        app(BookingService::class)->transition($booking, $this->admin, 'approved');
        $this->assertSame('booking_status', $this->staff->fresh()->unreadNotifications()->first()->data['kind']);
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_events', ['booking_id' => $booking->id, 'new_status' => 'approved']);
    }

    public function test_rescheduling_request_notifies_admin_and_review_notifies_staff(): void
    {
        $booking = Booking::create(array_merge($this->schedule(), ['user_id' => $this->staff->id, 'status' => 'approved']));
        $proposal = app(BookingService::class)->requestReschedule($booking, $this->staff, [
            'start_date' => '2026-11-06', 'end_date' => '2026-11-06',
            'start_time' => '10:00', 'end_time' => '11:00',
        ]);
        $this->assertSame('reschedule_requested', $this->admin->fresh()->unreadNotifications()->first()->data['kind']);
        app(BookingService::class)->decideReschedule($proposal, $this->admin, false, 'Room unavailable');
        $this->assertSame('reschedule_reviewed', $this->staff->fresh()->unreadNotifications()->first()->data['kind']);
    }

    public function test_notifications_are_private_and_only_the_owner_can_mark_read(): void
    {
        app(BookingService::class)->create($this->staff, $this->schedule());
        $notice = $this->admin->fresh()->unreadNotifications()->firstOrFail();
        $this->actingAs($this->other)->patch(route('notifications.read', $notice->id))->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
        $this->actingAs($this->admin)->get(route('notifications.index'))->assertOk()->assertSee('New booking');
        $this->patch(route('notifications.read', $notice->id))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_reminders_are_once_per_threshold_and_not_sent_after_approval(): void
    {
        $booking = Booking::create(array_merge($this->schedule('2026-11-03', '06:00'), [
            'user_id' => $this->staff->id, 'status' => 'pending',
        ]));
        $this->artisan('bookings:attention-reminders')->assertExitCode(0);
        $this->artisan('bookings:attention-reminders')->assertExitCode(0);
        $this->assertDatabaseCount('booking_attention_reminders', 1);
        $this->assertSame(1, $this->admin->fresh()->unreadNotifications()->count());
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(4, 0, 0));
        $this->artisan('bookings:attention-reminders')->assertExitCode(0);
        $this->artisan('bookings:attention-reminders')->assertExitCode(0);
        $this->assertDatabaseCount('booking_attention_reminders', 2);
        $this->assertSame(2, $this->admin->fresh()->unreadNotifications()->count());
        $booking->update(['status' => 'approved']);
        $this->artisan('bookings:attention-reminders')->assertExitCode(0);
        $this->assertDatabaseCount('booking_attention_reminders', 2);
    }
}
