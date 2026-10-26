<?php

declare(strict_types=1);

namespace BackToCode\PeilLaravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class PeilPing
{
    private const int TIMEOUT_SECONDS = 5;

    public static function send(string $monitor, string $url): void
    {
        if ($url === '') {
            return;
        }

        try {
            $response = Http::withoutRedirecting()
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException $e) {
            report(new PeilPingFailed($monitor, self::connectionFailure($e), $url));

            return;
        }

        if (! $response->successful()) {
            report(new PeilPingFailed($monitor, 'HTTP '.$response->status(), $url));
        }
    }

    // Laravel raises one ConnectionException for timeouts and refused connections alike.
    private static function connectionFailure(ConnectionException $e): string
    {
        return str_contains($e->getMessage(), 'cURL error 28') ? 'timeout' : 'connection error';
    }
}
