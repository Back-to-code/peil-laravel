<?php

declare(strict_types=1);

use BackToCode\PeilLaravel\PeilServiceProvider;
use Illuminate\Support\ServiceProvider;

it('publishes the config under the peil-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(PeilServiceProvider::class, 'peil-config');

    expect(array_values($paths))->toBe([config_path('peil.php')])
        ->and(realpath(array_key_first($paths)))->toBe(realpath(__DIR__.'/../../config/peil.php'));
});

it('is listed for auto-discovery', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toContain(PeilServiceProvider::class);
});

it('merges empty defaults', function (): void {
    expect(config('peil.queue_canary_url'))->toBe('')
        ->and(config('peil.monitors'))->toBe([]);
});
