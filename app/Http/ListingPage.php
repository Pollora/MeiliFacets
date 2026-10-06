<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

/**
 * Whether the request being served renders a listing. Hooks that fire in `wp_head` run on every page of the site
 * and have to ask; the listing root answers by rendering, which a view that extends its layout does before the head.
 */
final class ListingPage
{
    public const string FILTER = 'meilifacets/is_listing_page';

    private bool $isCurrent = false;

    public function markAsCurrent(): void
    {
        $this->isCurrent = true;
    }

    public function isCurrent(): bool
    {
        return (bool) apply_filters(self::FILTER, $this->isCurrent);
    }
}
