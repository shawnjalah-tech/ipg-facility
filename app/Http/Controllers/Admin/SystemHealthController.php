<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthController extends Controller
{
    private function heartbeat(string $file): ?array
    {
        try {
            $disk = Storage::disk('local');
            if (! $disk->exists($file)) {
                return null;
            }
            $data = json_decode($disk->get($file), true, 512, JSON_THROW_ON_ERROR);
            $timestamp = $data['succeeded_at'] ?? $data['processed_at'] ?? null;
            if (! is_string($timestamp)) {
                return null;
            }
            $time = CarbonImmutable::parse($timestamp);
            return [
                'at' => $time->toDayDateTimeString(),
                'fresh' => $time->lessThanOrEqualTo(now()) && $time->greaterThan(now()->subMinutes(3)),
            ];
        } catch (Throwable $exception) {
            report($exception);
            return null;
        }
    }

    public function index()
    {
        $expiration = $this->heartbeat('system-health/expiration.json');
        $queue = $this->heartbeat('system-health/queue.json');
        $queued = null;
        $failed = null;
        try {
            if (Schema::hasTable('jobs')) {
                $queued = DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('admin.system.health', [
            'expiration' => $expiration,
            'queue' => $queue,
            'queued' => $queued,
            'failed' => $failed,
            'queueDriver' => config('queue.default'),
            'mailDriver' => config('mail.default'),
            'registrationOpen' => (bool) config('facility.public_registration'),
            'productionDebug' => app()->environment('production') && (bool) config('app.debug'),
        ]);
    }
}
