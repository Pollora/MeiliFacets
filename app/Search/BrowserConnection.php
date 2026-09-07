<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Illuminate\Support\Uri;

/**
 * How the visitor's browser reaches the engine — a different address from the
 * one PHP uses, and a key that may only search. Both travel to the page, so the
 * key must never be the master one.
 */
final readonly class BrowserConnection
{
    public function __construct(
        public string $url,
        public string $key,
        public string $index,
    ) {}

    public function isConfigured(): bool
    {
        return $this->origin() !== '' && $this->key !== '' && $this->index !== '';
    }

    /** The origin to warm: a connection is opened per origin, not per path. */
    public function origin(): string
    {
        $uri = Uri::of($this->url);

        if ($uri->scheme() === null || $uri->host() === null) {
            return '';
        }

        return $uri->scheme().'://'.$uri->host().($uri->port() === null ? '' : ':'.$uri->port());
    }
}
