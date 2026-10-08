<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\UrlParameters;

/** One per name for the whole request: components placed anywhere share a single search. */
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
        private readonly ServiceUnavailable $serviceUnavailable,
        private readonly EngineLimits $limits,
    ) {}

    public function named(string $name): ResolvedListing
    {
        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /** What a template means when it names no listing. */
    public function onlyOne(): ResolvedListing
    {
        return $this->named($this->registry->onlyOne()->name());
    }

    private function resolve(string $name): ResolvedListing
    {
        $listing = $this->registry->named($name);
        $query = $this->requestQuery();

        return new ResolvedListing(
            $listing,
            $this->reader->read($listing, $query),
            $this->search,
            $this->values,
            $this->parameters,
            $this->serviceUnavailable,
            $this->limits,
        );
    }

    /**
     * WordPress resolved `/page/N` before we were asked: the path wins unless a parameter of ours says otherwise.
     *
     * @return array<string, mixed>
     */
    private function requestQuery(): array
    {
        $query = request()->query();
        $page = $this->parameters->reserved(QueryParameter::Page);

        return isset($query[$page]) ? $query : [...$query, $page => (string) get_query_var(PageAddress::PAGED_QUERY_VAR)];
    }
}
