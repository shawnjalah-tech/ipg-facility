<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/bootstrap.php';
$app = loadTestApplication();
$app->make(Kernel::class)->bootstrap();

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$run = realpath(getenv('IPG_LOAD_RUN_DIR'));
$mode = $argv[1] ?? '';

if ($mode === 'prepare') {
    if (file_exists($run.'/load.sqlite')) {
        throw new RuntimeException('Refusing to reuse an existing database. Start a new load run.');
    }
    touch($run.'/load.sqlite');
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    DB::statement('PRAGMA journal_mode = WAL');
    $password = Hash::make($input['password']);
    $users = [];
    for ($index = 0; $index < 50; $index++) {
        $role = $index >= 45 ? 'admin' : 'staff';
        $user = User::create([
            'name' => 'Load User '.($index + 1), 'email' => 'load'.($index + 1).'@example.test',
            'employee_id' => 'LOAD-'.($index + 1), 'password' => $password, 'role' => $role,
        ]);
        $users[] = [
            'id' => $user->id, 'email' => $user->email,
            'group' => $index < 25 ? 'booking' : ($index < 45 ? 'browsing' : 'admin'),
            'index' => $index,
        ];
    }
    $category = Category::create(['name' => 'Load Test Rooms']);
    $facilities = [];
    for ($index = 0; $index < 37; $index++) {
        $facilities[] = Facility::create([
            'category_id' => $category->id, 'name' => 'Load Room '.($index + 1),
            'capacity' => 40, 'is_active' => true,
        ])->id;
    }
    $history = (int) $input['history'];
    for ($index = 0; $index < $history; $index++) {
        $date = today()->subDays(60 + intdiv($index, 10))->toDateString();
        Booking::create([
            'user_id' => $users[$index % 45]['id'], 'facility_id' => $facilities[25 + $index % 10],
            'start_date' => $date, 'end_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00',
            'status' => ['pending', 'approved', 'rejected', 'cancelled', 'expired'][$index % 5],
            'purpose' => 'Seed history '.$index,
        ]);
    }
    $adminRace = app(BookingService::class)->create(User::findOrFail($users[0]['id']), [
        'facility_id' => $facilities[36], 'start_date' => today()->addDays(4)->toDateString(),
        'end_date' => today()->addDays(4)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
        'purpose' => 'Admin race',
    ]);
    echo json_encode([
        'users' => $users, 'facilities' => $facilities, 'history' => $history,
        'admin_race_id' => $adminRace->id, 'base_date' => today()->addDays(2)->toDateString(),
        'race_date' => today()->addDays(3)->toDateString(), 'timezone' => config('app.timezone'),
        'php' => PHP_VERSION, 'laravel' => $app->version(), 'bcrypt_rounds' => config('hashing.bcrypt.rounds'),
    ], JSON_THROW_ON_ERROR);
} elseif ($mode === 'verify') {
    $errors = [];
    $check = function (bool $condition, string $message) use (&$errors): void {
        if (! $condition) {
            $errors[] = $message;
        }
    };
    $rounds = (int) $input['rounds'];
    $history = (int) $input['history'];
    $check(User::count() === 50 && User::where('role', 'admin')->count() === 5, 'Expected exactly 45 staff and five admins.');
    $check(Booking::count() === $history + 25 * $rounds + 2, 'Unexpected total booking count.');
    $mixed = Booking::where('purpose', 'like', 'mixed:%')->with('events')->get();
    $expectedCounts = ['approved' => 5 * $rounds, 'rejected' => 5 * $rounds, 'cancelled' => 10 * $rounds, 'pending' => 5 * $rounds];
    $actualCounts = $mixed->countBy('status')->all();
    foreach ($expectedCounts as $status => $expected) {
        $check(($actualCounts[$status] ?? 0) === $expected, 'Wrong mixed-workload '.$status.' count.');
    }
    $byPurpose = $mixed->countBy('purpose');
    for ($round = 0; $round < $rounds; $round++) {
        for ($index = 0; $index < 25; $index++) {
            $check(($byPurpose['mixed:'.$round.':'.$index] ?? 0) === 1, 'Missing or duplicated logical booking.');
        }
    }
    foreach ($mixed as $booking) {
        [, $round, $index] = explode(':', $booking->purpose);
        $check($booking->user_id === User::where('email', 'load'.((int) $index + 1).'@example.test')->value('id'), 'Incorrect owner for '.$booking->purpose);
        $kind = (int) $index % 5;
        $expected = match ($kind) {
            0 => 'approved', 1 => 'rejected', 2, 3 => 'cancelled', default => 'pending',
        };
        $check($booking->status === $expected, 'Incorrect outcome for '.$booking->purpose);
        $check($booking->events->count() === ($kind === 3 ? 3 : ($kind === 4 ? 1 : 2)), 'Incorrect audit count for '.$booking->purpose);
        $check($booking->events->sortBy('id')->last()?->new_status === $booking->status, 'Latest audit state disagrees with booking '.$booking->id);
    }
    $race = Booking::where('purpose', 'booking-race')->get();
    $check($race->count() === 1, 'The single-slot race did not produce exactly one booking.');
    $decision = Booking::findOrFail($input['admin_race_id']);
    $check($decision->status === 'approved' && $decision->events()->count() === 2, 'The admin race did not produce exactly one decision event.');
    $overlaps = [];
    foreach (Booking::whereIn('status', ['pending', 'approved'])->get()->groupBy('facility_id') as $records) {
        $lastEnd = null;
        $lastId = null;
        foreach ($records->sortBy(fn (Booking $booking) => $booking->starts_at->timestamp) as $booking) {
            if ($lastEnd && $booking->starts_at->lt($lastEnd)) {
                $overlaps[] = [$lastId, $booking->id];
            }
            if (! $lastEnd || $booking->ends_at->gt($lastEnd)) {
                $lastEnd = $booking->ends_at;
                $lastId = $booking->id;
            }
        }
    }
    $check($overlaps === [], 'Overlapping active reservations were found.');
    $check(DB::select('PRAGMA foreign_key_check') === [], 'Foreign key integrity check failed.');
    echo json_encode([
        'passed' => $errors === [], 'errors' => $errors,
        'users' => User::count(), 'mixed_bookings' => $mixed->count(), 'mixed_statuses' => $actualCounts,
        'total_bookings' => Booking::count(), 'overlaps' => $overlaps,
        'booking_race_winners' => $race->count(), 'admin_race_decision_events' => $decision->events()->count() - 1,
    ], JSON_THROW_ON_ERROR);
} else {
    throw new RuntimeException('Expected prepare or verify mode.');
}
