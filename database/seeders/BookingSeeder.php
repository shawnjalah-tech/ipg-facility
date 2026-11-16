<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $bob    = User::where('email', 'bob@ipggaya.test')->first();
        $aisyah = User::where('email', 'aisyah@ipggaya.test')->first();
        $rajesh = User::where('email', 'rajesh@ipggaya.test')->first();

        $room101 = Facility::where('name', 'Room 101')->first();
        $room102 = Facility::where('name', 'Room 102')->first();
        $court   = Facility::where('name', 'Basketball Court')->first();
        $lab1    = Facility::where('name', 'Computer Lab 1')->first();

        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $bookings = [
            // Mr. Bob: Room 101 — Tue to Fri (matches the spec example)
            [$room101, $bob,    $monday->copy()->addDay(),     $monday->copy()->addDays(4), 'Project workshop', 'approved'],
            // Ms. Aisyah: Room 102 — Mon to Wed
            [$room102, $aisyah, $monday->copy(),               $monday->copy()->addDays(2), 'Team meeting',    'approved'],
            // Mr. Rajesh: Basketball Court — Sat
            [$court,   $rajesh, $monday->copy()->addDays(5),   $monday->copy()->addDays(5), 'Sports day prep', 'approved'],
            // Mr. Bob: Computer Lab 1 — pending (for admin to approve in Phase 6)
            [$lab1,    $bob,    $monday->copy()->addDays(2),   $monday->copy()->addDays(3), 'Coding class',    'pending'],
        ];

        foreach ($bookings as [$facility, $user, $start, $end, $purpose, $status]) {
            Booking::create([
                'facility_id' => $facility->id,
                'user_id'     => $user->id,
                'start_date'  => $start->toDateString(),
                'end_date'    => $end->toDateString(),
                'purpose'     => $purpose,
                'status'      => $status,
            ]);
        }
    }
}