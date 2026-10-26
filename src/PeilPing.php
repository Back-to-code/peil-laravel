<?php

declare(strict_types=1);

namespace BackToCode\PeilLaravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;

final readonly class PeilPing
{
    private const int TIMEOUT_SECONDS = 5;

    public function __construct(private Factory $http) {}

    public function send(string $monitor, string $url): void
    {
        if ($url === '') {
            return;
        }

        if (! $this->isHttps($url)) {
            report(new PeilPingFailed($monitor, 'not https', $url));

            return;
        }

        try {
            $response = $this->http->withoutRedirecting()
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException $e) {
            report(new PeilPingFailed($monitor, $this->connectionFailure($e), $url));

            return;
        } catch (RequestException $e) {
            report(new PeilPingFailed($monitor, 'HTTP '.$e->response->status(), $url));

            return;
        }

        if (! $response->successful()) {
            report(new PeilPingFailed($monitor, 'HTTP '.$response->status(), $url));
        }
    }

    // The URL is a secret: never put it on the wire in plaintext.
    private function isHttps(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && strtolower($scheme) === 'https';
    }

    // Laravel raises one ConnectionException for timeouts and refused connections alike.
    private function connectionFailure(ConnectionException $e): string
    {
        return str_contains($e->getMessage(), 'cURL error 28') ? 'timeout' : 'connection error';
    }
}
