# peil-laravel

Laravel integration for Peil: a queue canary and a `pingPeil` schedule macro.

Requires PHP 8.3+ and Laravel 12 or 13.

## Install

```bash
composer require back-to-code/peil-laravel
```

The service provider is auto-discovered.

## Queue canary

Set the ping URL of your Peil queue monitor:

```dotenv
PEIL_QUEUE_CANARY_URL=https://...
```

The package then schedules a queued job every five minutes on one server. When a queue worker runs it, the job sends a GET to that URL. With the variable unset or empty nothing is scheduled.

`onOneServer()` needs a cache store with atomic locks (Redis, database, Memcached or DynamoDB) as the default cache store.

A failed ping (timeout, connection error or non-2xx) never fails the job. It is passed to `report()` as a `BackToCode\PeilLaravel\PeilPingFailed`, which names the monitor, the failure and the host, never the URL path or query.

## Scheduled commands

Add a monitor URL per command in your env:

```dotenv
PEIL_MONITOR_REAPER_URL=https://...
```

Publish the config and list the monitors:

```bash
php artisan vendor:publish --tag=peil-config
```

```php
// config/peil.php
'monitors' => [
    'reaper' => env('PEIL_MONITOR_REAPER_URL'),
],
```

Then ping on success:

```php
Schedule::command('reaper:run')->hourly()->pingPeil('reaper');
```

`pingPeil` pings only when the command succeeded, and does nothing when the monitor entry is unset or empty. The URL is read when the schedule is defined, so it works with `config:cache`.

## License

MIT
