<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Listing\ListingState;

final readonly class ListingSearch
{
    private const string RESULTS = 'results';

    private const string COUNT = 'count:';

    public function __construct(
        private SearchEngine $engine,
        private FacetCounter $counter,
    ) {}

    public function run(Listing $listing, ListingState $state): SearchResults
    {
        $responses = $this->engine->multiSearch([
            self::RESULTS => QueryPlan::results($listing, $state),
            ...$this->countQueries($listing, $state),
        ]);

        $main = $responses[self::RESULTS] ?? [];

        return new SearchResults(
            array_values($main['hits'] ?? []),
            (int) ($main['estimatedTotalHits'] ?? 0),
            $this->distributions($listing, $responses),
        );
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
            $queries[self::COUNT.$taxonomy] = $query;
        }

        return $queries;
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     * @return array<string, array<string, int>>
     */
    private function distributions(Listing $listing, array $responses): array
    {
        $distributions = [];

        foreach ($listing->facets() as $facet) {
            $response = $responses[self::COUNT.$facet->taxonomy] ?? $responses[self::RESULTS] ?? [];
            $distributions[$facet->taxonomy] = $response['facetDistribution'][$facet->field()] ?? [];
        }

        return $distributions;
    }
}
