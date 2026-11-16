<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Support\LoadTestSandbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedLoadTest extends Command
{
    protected $signature = 'loadtest:seed {--verify-only : Check environment and database without inserting anything}';

    protected $description = 'Seed exactly 25 booking staff, 20 browsing staff and 5 administrators in an isolated load-test database.';

    public function handle(): int
    {
        if (! LoadTestSandbox::active()) {
            $this->error('Refusing to seed: APP_ENV must be loadtest and SQLite must point to database/loadtest.sqlite.');

            return self::FAILURE;
        }

        if ($this->option('verify-only')) {
            $this->info('SAFE: environment=loadtest and active DB=database/loadtest.sqlite (no data modified).');

            return self::SUCCESS;
        }

        $password = bin2hex(random_bytes(24));
        $hash = Hash::make($password);
        $category = Category::firstOrCreate(['name' => '[LOADTEST] Demo Facilities'], ['description' => 'Isolated performance-testing fixtures']);
        $facilityIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $facilityIds[] = Facility::updateOrCreate(
                ['name' => sprintf('[LOADTEST] Room %02d', $i)],
                ['category_id' => $category->id, 'description' => 'Synthetic load-testing room', 'capacity' => 40, 'is_active' => true]
            )->id;
        }
        $roles = ['booker' => [25, 'staff'], 'viewer' => [20, 'staff'], 'admin' => [5, 'admin']];
        foreach ($roles as $kind => [$count, $role]) {
            for ($i = 1; $i <= $count; $i++) {
                User::updateOrCreate(
                    ['email' => sprintf('loadtest-%s-%02d@example.invalid', $kind, $i)],
                    ['name' => sprintf('[LOADTEST] %s %02d', ucfirst($kind), $i),
                        'role' => $role, 'employee_id' => sprintf('LT-%s-%02d', strtoupper($kind), $i),
                        'password' => $hash]
                );
            }
        }
        $dir = storage_path('app/loadtest');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $fixtures = ['facility_ids' => $facilityIds, 'password' => $password, 'seeded_at' => now()->toIso8601String()];
        $path = $dir.'/fixtures.json';
        file_put_contents($path, json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        chmod($path, 0600);
        $this->info('Seeded 50 users (25 bookers / 20 viewers / 5 admins) and 10 facilities, ONLY in database/loadtest.sqlite.');
        $this->line('Locust fixtures: '.$path);

        return self::SUCCESS;
    }
}
