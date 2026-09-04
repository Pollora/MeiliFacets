<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

final class Unavailable
{
    private const int STATUS = 503;

    private const int RETRY_AFTER = 120;

    private bool $announced = false;

    /**
     * Varnish already refuses to cache a 503, but a listing may sit behind a
     * cache that does not.
     */
    public function announce(): void
    {
        if ($this->announced || headers_sent()) {
            return;
        }

        $this->announced = true;

        status_header(self::STATUS);
        header('Retry-After: '.self::RETRY_AFTER);
        header('Cache-Control: no-store');
    }

    public function announced(): bool
    {
        return $this->announced;
    }
}
