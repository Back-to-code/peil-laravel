<?php

declare(strict_types=1);

namespace BackToCode\PeilLaravel;

use RuntimeException;

final class PeilPingFailed extends RuntimeException
{
    // Host only, and no `previous`: the ping URL is a secret and the wrapped connection exception embeds it.
    public function __construct(string $monitor, string $failure, string $url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        parent::__construct(sprintf(
            'Peil ping for %s failed: %s (host %s)',
            $monitor,
            $failure,
            is_string($host) && $host !== '' ? $host : 'unknown',
        ));
    }
}
