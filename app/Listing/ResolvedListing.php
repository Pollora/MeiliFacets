<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\FilterExpression;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\SearchFailed;
use Modules\MeiliFacets\Search\SearchResults;
use Modules\MeiliFacets\Support\UrlParameters;
use RuntimeException;

final class ResolvedListing
{
    private ?SearchResults $results = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $cards = null;

    private ?Pagination $pages = null;

    private bool $failed = false;

    /** @var array<string, true> names already on the page, whatever placed them */
    private array $rendered = [];

    /** @var array<string, true> names a template placed on their own */
    private array $apart = [];

    public function __construct(
        private readonly Listing $listing,
        private readonly ListingState $state,
        private readonly ListingSearch $search,
        private readonly FacetValues $values,
        private readonly UrlParameters $parameters,
        private readonly Unavailable $unavailable,
        private readonly EngineLimits $limits,
    ) {}

    public function results(): SearchResults
    {
        return $this->results ??= $this->attempt();
    }

    public function failed(): bool
    {
        $this->results();

        return $this->failed;
    }

    private function attempt(): SearchResults
    {
        try {
            return $this->search->run($this->listing, $this->state);
        } catch (SearchFailed $failure) {
            $this->failed = true;
            $this->unavailable->announce();
            report($failure);

            return new SearchResults([], 0, []);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(): array
    {
        return $this->cards ??= $this->results()->cards();
    }

    /**
     * @return list<Facet>
     */
    public function facets(): array
    {
        return $this->listing->facets();
    }

    public function facetNamed(string $name): Facet
    {
        return array_find($this->facets(), static fn (Facet $facet): bool => $facet->name === $name)
            ?? throw new RuntimeException(
                "No facet named \"{$name}\" in listing \"{$this->name()}\". Declared: ".
                implode(', ', array_map(static fn (Facet $facet): string => $facet->name, $this->facets())).'.'
            );
    }

    /**
     * What `<x-meilifacets::facets>` shows: everything a template did not place on
     * its own.
     *
     * @return list<Facet>
     */
    public function remainingFacets(): array
    {
        return array_values(array_filter(
            $this->facets(),
            fn (Facet $facet): bool => ! isset($this->apart[$facet->name])
        ));
    }

    /** Designated by name, so the group leaves it alone. */
    public function placeApart(Facet $facet): void
    {
        $this->apart[$facet->name] = true;

        $this->place($facet);
    }

    public function place(Facet $facet): void
    {
        if (isset($this->rendered[$facet->name])) {
            throw new RuntimeException(
                "Facet \"{$facet->name}\" is rendered twice on this page: its inputs and ids "
                .'would be duplicated. Place it on its own before <x-meilifacets::facets>, which shows what is left.'
            );
        }

        $this->rendered[$facet->name] = true;
    }

    /**
     * @return list<FacetValue>
     */
    public function valuesOf(Facet $facet): array
    {
        return $this->values->of($facet, $this->results()->distribution($facet->taxonomy), $this->state);
    }

    public function pagination(): Pagination
    {
        return $this->pages ??= new Pagination(
            $this->state->page,
            $this->listing->perPage(),
            $this->results()->total,
            $this->limits->reachableHits,
        );
    }

    public function parameterFor(string $taxonomy): string
    {
        return $this->parameters->for($taxonomy);
    }

    public function activeFilterCount(): int
    {
        return $this->state->activeFilterCount();
    }

    public function name(): string
    {
        return $this->listing->name();
    }

    public function isPristine(): bool
    {
        return $this->state->isPristine();
    }

    public function perPage(): int
    {
        return $this->listing->perPage();
    }

    public function baseFilter(): string
    {
        return FilterExpression::all($this->listing->baseFilter());
    }

    public function applyMode(): ApplyMode
    {
        return $this->listing->applyMode();
    }

    /**
     * @return array<string, Sort>
     */
    public function sorts(): array
    {
        return $this->listing->sorts();
    }

    public function currentSort(): ?string
    {
        return $this->state->sort;
    }

    public function offset(): int
    {
        return $this->pagination()->offset();
    }
}
