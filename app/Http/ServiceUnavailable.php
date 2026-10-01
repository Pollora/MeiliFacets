<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

final class ServiceUnavailable
{
    private const int STATUS = 503;

    private const int RETRY_AFTER = 120;

    private bool $sent = false;

    /**
     * Varnish already refuses to cache a 503, but a listing may sit behind a
     * cache that does not.
     */
    public function sendHeaders(): void
    {
        if ($this->sent || headers_sent()) {
            return;
        }

        $this->sent = true;

        status_header(self::STATUS);
        header('Retry-After: '.self::RETRY_AFTER);
        header('Cache-Control: no-store');
    }
}
