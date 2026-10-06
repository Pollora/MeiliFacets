<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Symfony\Component\HttpFoundation\Response;

final class ServiceUnavailable
{
    private const int STATUS = 503;

    private const int RETRY_AFTER = 120;

    private const string NO_STORE = 'no-store';

    private bool $announced = false;

    public function announce(): void
    {
        $this->announced = true;
    }

    public function wasAnnounced(): bool
    {
        return $this->announced;
    }

    /**
     * Varnish already refuses to cache a 503, but a listing may sit behind a
     * cache that does not.
     */
    public function applyTo(Response $response): void
    {
        $response->setStatusCode(self::STATUS);
        $response->headers->set('Retry-After', (string) self::RETRY_AFTER);
        $response->headers->set('Cache-Control', self::NO_STORE);
        $response->headers->remove('Expires');
    }
}
