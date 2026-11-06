<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Modules\MeiliFacets\Listing\Pagination;
use Modules\MeiliFacets\Search\ResolvedListing;

/**
 * Whether the request being served renders a listing. Hooks that fire in `wp_head` run on every page of the site
 * and have to ask; the listing root answers by rendering, which a view that extends its layout does before the head.
 */
final class ListingPage
{
    public const string FILTER = 'meilifacets/is_listing_page';

    private bool $isCurrent = false;

    private ?ResolvedListing $listing = null;

    public function markAsCurrent(ResolvedListing $listing): void
    {
        $this->isCurrent = true;
        $this->listing ??= $listing;
    }

    public function isPaginatedBy(ResolvedListing $listing): bool
    {
        return $this->listing === $listing;
    }

    /** `null` when the engine did not answer: WordPress's own count is all there is. */
    public function pagination(): ?Pagination
    {
        if (! $this->listing instanceof ResolvedListing || $this->listing->failed()) {
            return null;
        }

        return $this->listing->pagination();
    }

    public function isCurrent(): bool
    {
        return (bool) apply_filters(self::FILTER, $this->isCurrent);
    }
}
