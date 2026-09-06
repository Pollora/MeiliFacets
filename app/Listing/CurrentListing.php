<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\UrlParameters;
use RuntimeException;

/**
 * One resolved listing per name for the whole request, so components placed
 * anywhere in the template share a single search.
 */
final class CurrentListing
{
    /** @var array<string, ResolvedListing> */
    private array $resolved = [];

    public function __construct(
        private readonly ListingRegistry $registry,
        private readonly ListingSearch $search,
        private readonly StateReader $reader,
        private readonly FacetValues $values,
        private readonly UrlParameters $parameters,
        private readonly Unavailable $unavailable,
    ) {}

    public function named(string $name): ResolvedListing
    {
        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /** What a template means when it names no listing. */
    public function sole(): ResolvedListing
    {
        return $this->named($this->registry->sole()->name());
    }

    private function resolve(string $name): ResolvedListing
    {
        $listing = $this->registry->get($name) ?? throw new RuntimeException(
            "No listing named \"{$name}\". Declared listings: ".implode(', ', $this->registry->names()).'.'
        );

        $query = $this->requestQuery();

        return new ResolvedListing(
            $listing,
            $this->reader->read($listing, $query),
            $this->search,
            $this->values,
            $this->parameters,
            $this->unavailable,
        );
    }

    /**
     * WordPress resolved `/page/N` before the listing was asked anything: the
     * path is followed when no parameter of our own says otherwise.
     *
     * @return array<string, mixed>
     */
    private function requestQuery(): array
    {
        $query = request()->query();
        $page = $this->parameters->reserved(QueryParameter::Page);

        return isset($query[$page]) ? $query : [...$query, $page => (string) get_query_var('paged')];
    }
}
