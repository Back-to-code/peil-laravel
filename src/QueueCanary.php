<?php

declare(strict_types=1);

namespace BackToCode\PeilLaravel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class QueueCanary implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $backoff = 0;

    // The URL is read from config on each run, never held on the job: it would serialize into the queue payload.
    public function handle(): void
    {
        PeilPing::send('queue canary', self::url());
    }

    public function failed(Throwable $e): void
    {
        report(new PeilPingFailed('queue canary', $e::class, self::url()));
    }

    private static function url(): string
    {
        return (string) config('peil.queue_canary_url', '');
    }
}
