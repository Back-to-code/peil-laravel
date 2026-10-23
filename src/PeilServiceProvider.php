<?php

declare(strict_types=1);

namespace BackToCode\PeilLaravel;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

final class PeilServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/peil.php', 'peil');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/peil.php' => config_path('peil.php')], 'peil-config');

        // Config is read when the event is defined, so it survives config:cache (env() returns null outside config files then).
        // Not pingOnSuccess: the framework reports the raw Guzzle exception, which embeds the URL, and waits 30 s.
        Event::macro('pingPeil', function (string $monitor): Event {
            /** @var Event $this */
            $url = (string) config("peil.monitors.{$monitor}", '');

            return $url === '' ? $this : $this->onSuccess(function () use ($monitor, $url): void {
                PeilPing::send($monitor, $url);
            });
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if ((string) config('peil.queue_canary_url', '') !== '') {
                $schedule->job(QueueCanary::class)->everyFiveMinutes()->onOneServer();
            }
        });
    }
}
