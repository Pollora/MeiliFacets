<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\SearchScopedListing;

/** What a listing filters and reads once a term is searched, typed by the visitor or routed by WordPress. */
final readonly class SearchScope
{
    /**
     * @param  list<string>  $filter  clauses every searched query carries, in place of the base filter
     * @param  list<string>|null  $fields  `attributesToSearchOn`; null searches every searchable attribute
     */
    public function __construct(
        public array $filter,
        public ?array $fields = null,
    ) {}

    public static function browsing(Listing $listing): self
    {
        return new self($listing->baseFilter());
    }

    public static function searching(Listing $listing): self
    {
        if ($listing instanceof SearchScopedListing) {
            return $listing->searchScope();
        }

        return self::browsing($listing);
    }
}
