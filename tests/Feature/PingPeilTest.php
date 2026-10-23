<?php

declare(strict_types=1);

use BackToCode\PeilLaravel\PeilPingFailed;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

const REAPER_URL = 'https://ping.peil.example/p/reaper99?token=SECRET';

function finishedEvent(object $schedule, int $exitCode): Event
{
    $event = $schedule->call(fn (): null => null)->pingPeil('reaper');
    $event->exitCode = $exitCode;
    $event->callAfterCallbacks(app());

    return $event;
}

it('pings the monitor url when the event succeeds', function (): void {
    config(['peil.monitors.reaper' => REAPER_URL]);
    Http::fake();

    finishedEvent(app(Schedule::class), 0);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === REAPER_URL);
});

it('does not ping when the event fails', function (): void {
    config(['peil.monitors.reaper' => REAPER_URL]);
    Http::fake();

    finishedEvent(app(Schedule::class), 1);

    Http::assertNothingSent();
});

it('is a no-op when the monitor entry is unset or empty', function (?string $url): void {
    config(['peil.monitors.reaper' => $url]);
    Http::fake();

    finishedEvent(app(Schedule::class), 0);

    Http::assertNothingSent();
})->with([null, '']);

it('reports one redacted PeilPingFailed and does not throw', function (Closure $fake, string $failure): void {
    config(['peil.monitors.reaper' => REAPER_URL]);
    $fake();
    Exceptions::fake();

    finishedEvent(app(Schedule::class), 0);

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(function (PeilPingFailed $e) use ($failure): bool {
        return str_contains($e->getMessage(), 'reaper')
            && str_contains($e->getMessage(), $failure)
            && str_contains($e->getMessage(), 'ping.peil.example')
            && ! str_contains($e->getMessage(), 'reaper99')
            && ! str_contains($e->getMessage(), 'SECRET')
            && $e->getPrevious() === null;
    });
})->with([
    'timeout' => [
        fn (): Factory => Http::fake(fn (): never => throw new ConnectionException('cURL error 28: Operation timed out for '.REAPER_URL)),
        'timeout',
    ],
    'connection refused' => [
        fn (): Factory => Http::fake(fn (): never => throw new ConnectionException('cURL error 7: Failed to connect to '.REAPER_URL)),
        'connection error',
    ],
    'non-2xx' => [
        fn (): Factory => Http::fake(fn (): PromiseInterface => Http::response('down', 503)),
        'HTTP 503',
    ],
]);
