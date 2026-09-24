<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\FacetTruncated;
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

    /** @var list<Facet>|null */
    private ?array $facets = null;

    /** @var list<Placeable>|null */
    private ?array $filters = null;

    /** @var array<string, list<FacetValue>> */
    private array $valuesByFacet = [];

    /** @var array<string, Sort>|null */
    private ?array $sorts = null;

    private readonly PagePlacement $placement;

    public function __construct(
        private readonly Listing $listing,
        private readonly ListingState $state,
        private readonly ListingSearch $search,
        private readonly FacetValues $facetValues,
        private readonly UrlParameters $parameters,
        private readonly Unavailable $unavailable,
        private readonly EngineLimits $limits,
    ) {
        $this->placement = new PagePlacement($listing->name());
    }

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
     * What `<x-meilifacets::facets>` and `<x-meilifacets::facet>` place: term
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
                throw new RuntimeException(sprintf(
                    'Facets "%s" and "%s" answer to the same name "%s" in listing "%s". Declare `name:` on all but one of them.',
                    $labels[$filter->name], $filter->label, $filter->name, $this->name(),
                ));
            }

            $labels[$filter->name] = $filter->label;
        }

        return $filters;
    }

    private function facetNamed(string $name): Placeable
    {
        return array_find($this->filters(), static fn (Placeable $filter): bool => $filter->name === $name)
            ?? throw new RuntimeException(
                "No facet named \"{$name}\" in listing \"{$this->name()}\". Declared: ".
                implode(', ', array_map(static fn (Placeable $filter): string => $filter->name, $this->filters())).'.'
            );
    }

    /**
     * What `<x-meilifacets::facets>` shows: everything a template did not place on
     * its own.
     *
     * @return list<Placeable>
     */
    public function remainingFacets(): array
    {
        return $this->placement->remaining($this->filters());
    }

    /**
     * A declaration comes from the group and stays in it; a name comes from a template
     * that placed the facet on its own, so the group leaves it out. A component only
     * places its own kind, so a mismatch is caught here rather than rendered wrong.
     *
     * @template T of Placeable
     *
     * @param  class-string<T>  $kind
     * @return T
     */
    public function placeFacet(Placeable|string $facet, string $kind): Placeable
    {
        if ($facet instanceof Placeable) {
            $this->placement->place($this->ofKind($facet, $kind));

            return $facet;
        }

        $named = $this->ofKind($this->facetNamed($facet), $kind);
        $this->placement->placeApart($named);

        return $named;
    }

    /**
     * @template T of Placeable
     *
     * @param  class-string<T>  $kind
     * @return T
     */
    private function ofKind(Placeable $facet, string $kind): Placeable
    {
        if (! $facet instanceof $kind) {
            throw new RuntimeException(sprintf(
                '"%s" in listing "%s" is a %s: place it with the component of its own kind.',
                $facet->name,
                $this->name(),
                class_basename($facet)
            ));
        }

        return $facet;
    }

    public function placeSort(): void
    {
        $this->placement->placeSort();
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

    public function parameterForReserved(QueryParameter $parameter): string
    {
        return $this->parameters->reserved($parameter);
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

    public function baseQuery(): string
    {
        return $this->listing->baseQuery();
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

    public function currentSort(): ?string
    {
        return $this->state->sort;
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
