<?php

declare(strict_types=1);

use BackToCode\PeilLaravel\PeilPingFailed;
use BackToCode\PeilLaravel\QueueCanary;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const CANARY_URL = 'https://ping.peil.example/p/abc123?token=SECRET';

/** @return list<CallbackEvent> */
function canaryEvents(Schedule $schedule): array
{
    return array_values(array_filter(
        $schedule->events(),
        fn (object $event): bool => $event instanceof CallbackEvent && $event->description === QueueCanary::class,
    ));
}

dataset('ping failures', [
    'timeout' => [
        fn (): Factory => Http::fake(fn (): never => throw new ConnectionException('cURL error 28: Operation timed out for '.CANARY_URL)),
        'timeout',
    ],
    'connection refused' => [
        fn (): Factory => Http::fake(fn (): never => throw new ConnectionException('cURL error 7: Failed to connect to '.CANARY_URL)),
        'connection error',
    ],
    'non-2xx' => [
        fn (): Factory => Http::fake(fn (): PromiseInterface => Http::response('down', 503)),
        'HTTP 503',
    ],
]);

it('schedules the canary every five minutes on one server when the url is set', function (): void {
    config(['peil.queue_canary_url' => CANARY_URL]);

    $events = canaryEvents($this->freshSchedule());

    expect($events)->toHaveCount(1)
        ->and($events[0]->expression)->toBe('*/5 * * * *')
        ->and($events[0]->onOneServer)->toBeTrue();
});

it('schedules nothing when the url is unset or empty', function (?string $url): void {
    config(['peil.queue_canary_url' => $url]);

    expect(canaryEvents($this->freshSchedule()))->toBeEmpty();
})->with([null, '']);

it('queues the scheduled canary instead of pinging from the scheduler', function (): void {
    config(['peil.queue_canary_url' => CANARY_URL]);
    Queue::fake();
    Http::fake();

    canaryEvents($this->freshSchedule())[0]->run($this->app);

    Queue::assertPushed(QueueCanary::class, 1);
    Http::assertNothingSent();
});

it('GETs the configured url when the job runs', function (): void {
    config(['peil.queue_canary_url' => CANARY_URL]);
    Http::fake();

    (new QueueCanary)->handle();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === CANARY_URL);
});

it('bounds the ping with a 5 s timeout and connect timeout', function (): void {
    config(['peil.queue_canary_url' => CANARY_URL]);
    $options = [];
    Http::fake(function (Request $request, array $given) use (&$options): PromiseInterface {
        $options = $given;

        return Http::response('ok');
    });

    (new QueueCanary)->handle();

    expect($options['timeout'])->toBe(5)
        ->and($options['connect_timeout'])->toBe(5);
});

it('reports one redacted PeilPingFailed and does not throw', function (Closure $fake, string $failure): void {
    config(['peil.queue_canary_url' => CANARY_URL]);
    $fake();
    Exceptions::fake();

    (new QueueCanary)->handle();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(function (PeilPingFailed $e) use ($failure): bool {
        return str_contains($e->getMessage(), 'queue canary')
            && str_contains($e->getMessage(), $failure)
            && str_contains($e->getMessage(), 'ping.peil.example')
            && ! str_contains($e->getMessage(), 'abc123')
            && ! str_contains($e->getMessage(), 'SECRET')
            && $e->getPrevious() === null;
    });
})->with('ping failures');

it('runs once with no backoff', function (): void {
    $job = new QueueCanary;

    expect($job->tries)->toBe(1)->and($job->backoff)->toBe(0);
});

it('keeps the url out of the serialized job', function (): void {
    config(['peil.queue_canary_url' => CANARY_URL]);

    expect(serialize(new QueueCanary))->not->toContain('abc123')->not->toContain('SECRET');
});

it('reports a redacted PeilPingFailed from failed()', function (): void {
    config(['peil.queue_canary_url' => CANARY_URL]);
    Exceptions::fake();

    (new QueueCanary)->failed(new RuntimeException('worker died calling '.CANARY_URL));

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (PeilPingFailed $e): bool => str_contains($e->getMessage(), 'queue canary')
        && str_contains($e->getMessage(), 'ping.peil.example')
        && ! str_contains($e->getMessage(), 'abc123')
        && ! str_contains($e->getMessage(), 'SECRET')
        && $e->getPrevious() === null);
});
