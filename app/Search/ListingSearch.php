<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;

final readonly class ListingSearch
{
    private const string RESULTS = 'results';

    private const string UNFILTERED = 'unfiltered';

    public function __construct(
        private SearchEngine $engine,
        private FacetCounter $counter,
    ) {}

    public function run(Listing $listing, ListingState $state): SearchResults
    {
        $responses = $this->engine->multiSearch($this->searches($listing, $state));
        $main = $responses[self::RESULTS] ?? [];

        return new SearchResults(
            array_values($main['hits'] ?? []),
            (int) ($main['totalHits'] ?? 0),
            $this->distributions($listing, $responses),
            $this->facetStats($responses),
            $this->unfilteredDistributions($listing, $responses),
            $this->sortMatches($listing, $main),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function searches(Listing $listing, ListingState $state): array
    {
        $measuredApart = [
            ...$this->countQueries($listing, $state),
            ...$this->boundsQueries($listing, $state),
        ];

        return [
            self::RESULTS => QueryPlan::results($listing, $state, array_keys($measuredApart)),
            ...$measuredApart,
            ...$this->unfilteredQueries($listing, $state),
        ];
    }

    /**
     * Counting searches are keyed apart from the results, so no taxonomy name
     * can take the place of another.
     *
     * @return array<string, array<string, mixed>>
     */
    private function countQueries(Listing $listing, ListingState $state): array
    {
        $queries = [];

        foreach ($this->counter->queries($listing, $state) as $taxonomy => $query) {
            $queries[FacetQuery::keyFor($taxonomy)] = $query;
        }

        return $queries;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function boundsQueries(Listing $listing, ListingState $state): array
    {
        $price = PriceFilter::among($listing->filters());

        if (! $price instanceof PriceFilter) {
            return [];
        }

        $query = new PriceQuery($price);

        return $query->isMeasuredApart($state) ? [$query->key() => QueryPlan::apart($listing, $state, $query)] : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function unfilteredQueries(Listing $listing, ListingState $state): array
    {
        return $listing->facets() !== [] && $this->isNarrowed($listing, $state) ? [self::UNFILTERED => QueryPlan::unfiltered($listing)] : [];
    }

    private function isNarrowed(Listing $listing, ListingState $state): bool
    {
        if ($state->query !== '') {
            return true;
        }

        return array_any(QueryPlan::filterQueries($listing), static fn (FilterQuery $filter): bool => $filter->clause($state) !== '');
    }

    /**
     * @param  array<string, mixed>  $main
     * @return array<string, int>
     */
    private function sortMatches(Listing $listing, array $main): array
    {
        return QueryPlan::sortQuery($listing)?->matchesIn($main['facetDistribution'] ?? []) ?? [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @return array<string, array<string, int>>
     */
    private function unfilteredDistributions(Listing $listing, array $responses): array
    {
        if (! isset($responses[self::UNFILTERED])) {
            return [];
        }

        $distributions = [];

        foreach ($listing->facets() as $facet) {
            $distributions[$facet->taxonomy] = $responses[self::UNFILTERED]['facetDistribution'][$facet->field()] ?? [];
        }

        return $distributions;
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @return array<string, array<string, float>>
     */
    private function facetStats(array $responses): array
    {
        return ($responses[PriceQuery::KEY] ?? $responses[self::RESULTS] ?? [])['facetStats'] ?? [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @return array<string, array<string, int>>
     */
    private function distributions(Listing $listing, array $responses): array
    {
        $distributions = [];

        foreach ($listing->facets() as $facet) {
            $response = $responses[FacetQuery::keyFor($facet->taxonomy)] ?? $responses[self::RESULTS] ?? [];
            $distributions[$facet->taxonomy] = $response['facetDistribution'][$facet->field()] ?? [];
        }

        return $distributions;
    }
}
