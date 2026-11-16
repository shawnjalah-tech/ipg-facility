<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Demo staff accounts can only be created in a local development copy.');
        }

        $staff = [
            ['name' => 'Mr. Bob',        'email' => 'bob@ipggaya.test',     'employee_id' => 'STF-001'],
            ['name' => 'Ms. Aisyah',     'email' => 'aisyah@ipggaya.test',  'employee_id' => 'STF-002'],
            ['name' => 'Mr. Rajesh',     'email' => 'rajesh@ipggaya.test',  'employee_id' => 'STF-003'],
        ];

        foreach ($staff as $s) {
            User::create([
                'name' => $s['name'],
                'email' => $s['email'],
                'password' => Str::random(48),
                'role' => 'staff',
                'employee_id' => $s['employee_id'],
            ]);
        }
    }
}
