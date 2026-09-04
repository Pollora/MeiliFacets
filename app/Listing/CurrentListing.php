<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Discovery\ListingRegistry;
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
            new ListingUrls($this->parameters, $this->path()),
            $this->unavailable,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestQuery(): array
    {
        return request()->query();
    }

    private function path(): string
    {
        return (string) parse_url(request()->fullUrl(), PHP_URL_PATH);
    }
}
