<?php

namespace Tests\Feature;

use App\Jobs\QueueHealthProbe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseTwoSecurityAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_can_be_closed_without_disabling_admin_user_creation(): void
    {
        config()->set('facility.public_registration', false);
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Unauthorized', 'email' => 'unauthorized@example.test',
            'password' => 'Password1234', 'password_confirmation' => 'Password1234',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
        $this->get('/login')->assertOk()->assertDontSee('Register as staff');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Admin Provisioned Staff', 'email' => 'managed@example.test',
            'role' => 'staff', 'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'managed@example.test', 'role' => 'staff']);
    }

    public function test_public_registration_when_explicitly_enabled_assigns_only_staff_role(): void
    {
        config()->set('facility.public_registration', true);
        $this->get('/register')->assertOk();
        $this->post('/register', [
            'name' => 'Test Account', 'email' => 'test-person@example.test',
            'role' => 'admin', 'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'test-person@example.test', 'role' => 'staff']);
    }

    public function test_staff_only_routes_cannot_be_used_by_administrators(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin)->get('/staff/my-bookings')->assertForbidden();
        $this->actingAs($admin)->post('/staff/bookings', [])->assertForbidden();
        $this->actingAs($staff)->get('/staff/my-bookings')->assertOk();
        $this->actingAs($staff)->get('/admin/system/health')->assertForbidden();
    }

    public function test_health_dashboard_is_admin_only_and_does_not_leak_secrets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        config()->set('mail.default', 'log');
        config()->set('mail.mailers.smtp.password', 'TOP_SECRET_DO_NOT_SHOW');
        $this->get('/admin/system/health')->assertRedirect('/login');
        $this->actingAs($admin)->get('/admin/system/health')
            ->assertOk()
            ->assertSee('System Health')
            ->assertSee('Not delivering email')
            ->assertDontSee('TOP_SECRET_DO_NOT_SHOW');
    }

    public function test_queue_probe_writes_private_heartbeat(): void
    {
        Storage::fake('local');
        (new QueueHealthProbe)->handle();
        Storage::disk('local')->assertExists('system-health/queue.json');
        $data = json_decode(Storage::disk('local')->get('system-health/queue.json'), true);
        $this->assertArrayHasKey('processed_at', $data);
    }

    public function test_malformed_login_fields_are_validation_errors(): void
    {
        $this->postJson('/login', ['email' => ['invalid'], 'password' => ['invalid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_refused_self_demotion_does_not_flash_the_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $password = bin2hex(random_bytes(16));

        $response = $this->actingAs($admin)->from(route('admin.users.edit', $admin))
            ->put(route('admin.users.update', $admin), [
                'name' => 'Retain this profile field',
                'email' => $admin->email,
                'role' => 'staff',
                'password' => $password,
                'password_confirmation' => $password,
            ]);

        $response->assertRedirect(route('admin.users.edit', $admin))->assertSessionHas('error');
        $response->assertSessionHas('_old_input.name', 'Retain this profile field');
        $response->assertSessionMissing('_old_input.password');
        $response->assertSessionMissing('_old_input.password_confirmation');
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
