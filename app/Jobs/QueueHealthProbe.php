<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class QueueHealthProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(): void
    {
        Storage::disk('local')->put('system-health/queue.json', json_encode([
            'processed_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }
}
