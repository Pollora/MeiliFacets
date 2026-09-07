<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\SearchFailed;
use Modules\MeiliFacets\Search\SearchResults;
use Modules\MeiliFacets\Support\UrlParameters;

final class ResolvedListing
{
    private ?SearchResults $results = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $cards = null;

    private ?Pagination $pages = null;

    private bool $failed = false;

    public function __construct(
        private readonly Listing $listing,
        private readonly ListingState $state,
        private readonly ListingSearch $search,
        private readonly FacetValues $values,
        private readonly UrlParameters $parameters,
        private readonly Unavailable $unavailable,
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

    /**
     * @return list<FacetValue>
     */
    public function valuesOf(Facet $facet): array
    {
        return $this->values->of($facet, $this->results()->distribution($facet->taxonomy), $this->state);
    }

    public function pagination(): Pagination
    {
        return $this->pages ??= new Pagination($this->state->page, $this->listing->perPage(), $this->results()->total);
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
