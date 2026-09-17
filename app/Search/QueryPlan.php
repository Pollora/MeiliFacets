<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;

final readonly class QueryPlan
{
    private const int NO_HIT = 0;

    /**
     * @return list<FilterQuery>
     */
    public static function filterQueries(Listing $listing): array
    {
        $price = PriceFilter::among($listing->filters());

        // Asking the engine for price bounds brings back a distribution of every distinct price too.
        return [
            ...array_map(static fn (Facet $facet): FilterQuery => new FacetQuery($facet), $listing->facets()),
            ...$price instanceof PriceFilter ? [new PriceQuery($price)] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function results(Listing $listing, ListingState $state): array
    {
        $filters = self::filterQueries($listing);
        $query = [
            'q' => $state->query,
            'filter' => self::filter($listing, $state, $filters),
            'facets' => self::fieldsOnMain($filters, $state),
            // `hitsPerPage`/`page` answer with `totalHits` and `totalPages`;
            // `limit`/`offset` only give an estimate, capped at maxTotalHits.
            'hitsPerPage' => $listing->perPage(),
            'page' => $state->page,
            'attributesToRetrieve' => [DocumentField::Card->value],
        ];

        $sort = $listing->sorts()[$state->sort] ?? null;

        return $sort === null ? $query : [...$query, 'sort' => $sort->expressions];
    }

    /**
     * @return array<string, mixed>
     */
    public static function apart(Listing $listing, ListingState $state, FilterQuery $lifted): array
    {
        $others = array_filter(
            self::filterQueries($listing),
            static fn (FilterQuery $filter): bool => $filter->key() !== $lifted->key()
        );

        return [
            'q' => $state->query,
            'filter' => self::filter($listing, $state, $others),
            'facets' => $lifted->fields(),
            'hitsPerPage' => self::NO_HIT,
            'page' => ListingState::FIRST_PAGE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function unfiltered(Listing $listing): array
    {
        return [
            'q' => '',
            'filter' => FilterExpression::all($listing->baseFilter()),
            'facets' => array_map(static fn (Facet $facet): string => $facet->field(), $listing->facets()),
            'hitsPerPage' => self::NO_HIT,
            'page' => ListingState::FIRST_PAGE,
        ];
    }

    /**
     * @param  array<FilterQuery>  $filters
     */
    private static function filter(Listing $listing, ListingState $state, array $filters): string
    {
        return FilterExpression::all([
            ...$listing->baseFilter(),
            ...array_map(static fn (FilterQuery $filter): string => $filter->clause($state), array_values($filters)),
        ]);
    }

    /**
     * @param  list<FilterQuery>  $filters
     * @return list<string>
     */
    private static function fieldsOnMain(array $filters, ListingState $state): array
    {
        $onMain = array_filter($filters, static fn (FilterQuery $filter): bool => ! $filter->isMeasuredApart($state));

        return array_merge(...array_map(static fn (FilterQuery $filter): array => $filter->fields(), array_values($onMain)));
    }
}
