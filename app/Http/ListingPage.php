<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

/**
 * Whether the request being served is one a listing is rendered on. Hooks that
 * fire in `wp_head` run on every page of the site and have to ask.
 */
final readonly class ListingPage
{
    public function isCurrent(): bool
    {
        return is_archive() || is_search();
    }
}
