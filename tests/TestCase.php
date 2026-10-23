<?php

declare(strict_types=1);

namespace BackToCode\PeilLaravel\Tests;

use BackToCode\PeilLaravel\PeilServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PeilServiceProvider::class];
    }

    // The schedule hook reads config when Schedule resolves, so drop the instance resolved during boot.
    protected function freshSchedule(): Schedule
    {
        $this->app->forgetInstance(Schedule::class);

        return $this->app->make(Schedule::class);
    }
}
