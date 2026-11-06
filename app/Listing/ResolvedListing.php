<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\CardComponentListing;
use Modules\MeiliFacets\Contracts\DefaultSortedListing;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Contracts\VariantScopedListing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\EngineUnavailable;
use Modules\MeiliFacets\Search\FacetTruncated;
use Modules\MeiliFacets\Search\FilterExpression;
use Modules\MeiliFacets\Search\ListingResults;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\QueryPlan;

final class ResolvedListing
{
    private ?ListingResults $results = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $cards = null;

    private ?Pagination $pages = null;

    private bool $failed = false;

    /** @var list<Facet>|null */
    private ?array $facets = null;

    /** @var list<Placeable>|null */
    private ?array $filters = null;

    /** @var array<string, list<FacetValue>> */
    private array $valuesByFacet = [];

    /** @var array<string, Sort>|null */
    private ?array $sorts = null;

    private ?PagePlacement $placement = null;

    public function __construct(
        private readonly Listing $listing,
        private readonly ListingState $state,
        private readonly ListingSearch $search,
        private readonly FacetValues $facetValues,
        private readonly EngineLimits $limits,
    ) {}

    public function results(): ListingResults
    {
        return $this->results ??= $this->attempt();
    }

    public function failed(): bool
    {
        $this->results();

        return $this->failed;
    }

    private function attempt(): ListingResults
    {
        try {
            return $this->search->run($this->listing, $this->state);
        } catch (EngineUnavailable $failure) {
            $this->failed = true;
            report($failure);

            return new ListingResults([], 0, []);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(): array
    {
        return $this->cards ??= $this->shownCards();
    }

    /**
     * The cards before any variant is chosen: what a product is, whatever the filters point to.
     *
     * @return list<array<string, mixed>>
     */
    public function projectedCards(): array
    {
        return $this->results()->cards();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shownCards(): array
    {
        $choice = new VariantChoice($this->state, $this->variantTaxonomies(), $this->sortFilter());

        return array_map($choice->shown(...), $this->projectedCards());
    }

    private function sortFilter(): ?SortFilter
    {
        return $this->state->chosenSort($this->sorts())?->filter;
    }

    /**
     * @return list<string>
     */
    private function variantTaxonomies(): array
    {
        return $this->variantListing()?->variantTaxonomies() ?? [];
    }

    public function state(): ListingState
    {
        return $this->state;
    }

    /**
     * Raw engine statistics. Which fields mean what is the filter's business.
     *
     * @return array<string, array<string, float>>
     */
    public function facetStats(): array
    {
        return $this->results()->facetStats;
    }

    /**
     * @return list<Facet>
     */
    public function facets(): array
    {
        return $this->facets ??= $this->listing->facets();
    }

    /**
     * What `<x-meilifacets::listing.facets>` and `<x-meilifacets::listing.facet>` place: term
     * facets and the price range alike.
     *
     * @return list<Placeable>
     */
    public function filters(): array
    {
        return $this->filters ??= $this->refuseSharedNames($this->listing->filters());
    }

    /**
     * @param  list<Placeable>  $filters
     * @return list<Placeable>
     */
    private function refuseSharedNames(array $filters): array
    {
        $labels = [];

        foreach ($filters as $filter) {
            if (isset($labels[$filter->name])) {
                throw PlacementRefused::sharedName($labels[$filter->name], $filter->label, $filter->name, $this->name());
            }

            $labels[$filter->name] = $filter->label;
        }

        return $filters;
    }

    public function placement(): PagePlacement
    {
        return $this->placement ??= new PagePlacement($this->name(), $this->filters(), $this->isInCatalog(...));
    }

    public function isShown(Placeable $filter): bool
    {
        return match (true) {
            $filter instanceof PriceFilter => $this->hasPriceBounds($filter),
            $filter instanceof Facet => $this->hasReadableValues($filter),
            default => false,
        };
    }

    private function isInCatalog(Placeable $filter): bool
    {
        if (! $this->search->narrows($this->listing, $this->state)) {
            return $this->isShown($filter);
        }

        return match (true) {
            // The unfiltered query carries no price statistics.
            $filter instanceof PriceFilter => true,
            $filter instanceof Facet => $this->hasCatalogValues($filter),
            default => false,
        };
    }

    /** A facet the listing does not measure, like the flat one of the taxonomy a page pins, has none. */
    private function hasCatalogValues(Facet $facet): bool
    {
        return ($this->results()->unfilteredDistribution($facet->taxonomy) ?? []) !== [];
    }

    private function hasPriceBounds(PriceFilter $filter): bool
    {
        return ! $filter->boundsFrom($this->facetStats())->isEmpty();
    }

    private function hasReadableValues(Facet $facet): bool
    {
        return array_any($this->valuesOf($facet), static fn (FacetValue $value): bool => ! $value->folded);
    }

    /**
     * @return list<FacetValue>
     */
    public function valuesOf(Facet $facet): array
    {
        return $this->valuesByFacet[$facet->name] ??= $this->resolveValues($facet);
    }

    /**
     * The words the page shows for each value it rendered, folded ones included.
     *
     * @return array<string, string> slug to label
     */
    public function labelsOf(Facet $facet): array
    {
        return array_column(array_map(
            static fn (FacetValue $value): array => [$value->slug, $value->label],
            $this->valuesOf($facet),
        ), 1, 0);
    }

    /**
     * @return list<FacetValue>
     */
    private function resolveValues(Facet $facet): array
    {
        $results = $this->results();
        $distribution = $results->distribution($facet->taxonomy);
        $unfiltered = $results->unfilteredDistribution($facet->taxonomy);
        $received = max(count($distribution), count($unfiltered ?? []));

        if ($this->limits->looksTruncated($received)) {
            report(FacetTruncated::at($facet->taxonomy, $received));
        }

        return $this->facetValues->of($facet, $distribution, $this->state, $unfiltered);
    }

    public function pagination(): Pagination
    {
        return $this->pages ??= new Pagination(
            $this->state->page,
            $this->listing->perPage(),
            $this->total(),
            $this->limits->reachableHits,
        );
    }

    public function total(): int
    {
        return $this->results()->total;
    }

    /**
     * @return list<string>
     */
    public function selectedIn(Facet $facet): array
    {
        return $this->state->selected($facet->taxonomy);
    }

    public function name(): string
    {
        return $this->listing->name();
    }

    public function perPage(): int
    {
        return $this->listing->perPage();
    }

    /**
     * @return list<string>
     */
    public function baseFilter(): array
    {
        return $this->listing->baseFilter();
    }

    public function baseFilterExpression(): string
    {
        return FilterExpression::all($this->baseFilter());
    }

    public function baseQuery(): string
    {
        return $this->listing->baseQuery();
    }

    public function searchScope(): SearchScope
    {
        return QueryPlan::searchScope($this->listing);
    }

    public function variantListing(): ?VariantScopedListing
    {
        return $this->listing instanceof VariantScopedListing ? $this->listing : null;
    }

    public function isRoutedSearch(): bool
    {
        return StateReader::isRoutedSearch($this->listing);
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
        return $this->sorts ??= $this->listing->sorts();
    }

    /** The card the listing declares, if it declares one. */
    public function cardComponent(): ?string
    {
        return $this->listing instanceof CardComponentListing ? $this->listing->cardComponent() : null;
    }

    /**
     * @return list<string>
     */
    public function defaultSort(): array
    {
        return $this->listing instanceof DefaultSortedListing ? array_values($this->listing->defaultSort()) : [];
    }

    /**
     * @return array<string, int>
     */
    public function sortMatches(): array
    {
        return $this->results()->sortMatches;
    }

    public function offset(): int
    {
        return $this->pagination()->offset();
    }
}
