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

    private const string MEASURES = 'measures';

    public function __construct(
        private SearchEngine $engine,
        private FacetCounter $counter,
    ) {}

    public function run(Listing $listing, ListingState $state): ListingResults
    {
        $responses = $this->engine->multiSearch($this->searches($listing, $state));
        $results = $responses[self::RESULTS] ?? [];

        return new ListingResults(
            array_values($results['hits'] ?? []),
            (int) ($results['totalHits'] ?? 0),
            $this->distributions($listing, $responses),
            $this->facetStats($responses),
            $this->unfilteredDistributions($listing, $responses),
            $this->sortMatches($listing, $this->measuresIn($responses)),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function searches(Listing $listing, ListingState $state): array
    {
        $measuredSeparately = [
            ...$this->countQueries($listing, $state),
            ...$this->boundsQueries($listing, $state),
        ];

        return [
            self::RESULTS => QueryPlan::results($listing, $state, array_keys($measuredSeparately)),
            ...$this->measureQueries($listing, $state, array_keys($measuredSeparately)),
            ...$measuredSeparately,
            ...$this->unfilteredQueries($listing, $state),
        ];
    }

    /**
     * @param  list<string>  $measuredSeparately
     * @return array<string, array<string, mixed>>
     */
    private function measureQueries(Listing $listing, ListingState $state, array $measuredSeparately): array
    {
        if (! QueryPlan::readsVariants($listing, $state)) {
            return [];
        }

        return [self::MEASURES => QueryPlan::measures($listing, $state, $measuredSeparately)];
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @return array<string, mixed>
     */
    private function measuresIn(array $responses): array
    {
        return $responses[self::MEASURES] ?? $responses[self::RESULTS] ?? [];
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

        return $query->isMeasuredSeparately($state) ? [$query->key() => QueryPlan::measureWithout($listing, $state, $query)] : [];
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
     * @param  array<string, mixed>  $measures
     * @return array<string, int>
     */
    private function sortMatches(Listing $listing, array $measures): array
    {
        return QueryPlan::sortQuery($listing)?->matchesIn($measures['facetDistribution'] ?? []) ?? [];
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
        return ($responses[PriceQuery::KEY] ?? $this->measuresIn($responses))['facetStats'] ?? [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @return array<string, array<string, int>>
     */
    private function distributions(Listing $listing, array $responses): array
    {
        $distributions = [];

        foreach ($listing->facets() as $facet) {
            $response = $responses[FacetQuery::keyFor($facet->taxonomy)] ?? $this->measuresIn($responses);
            $distributions[$facet->taxonomy] = $response['facetDistribution'][$facet->field()] ?? [];
        }

        return $distributions;
    }
}
