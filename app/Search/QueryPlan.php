<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Listing\StateReader;

final readonly class QueryPlan
{
    private const int NO_HIT = 0;

    /**
     * @return list<FilterQuery>
     */
    public static function filterQueries(Listing $listing): array
    {
        $price = PriceFilter::among($listing->filters());
        $sortQuery = self::sortQuery($listing);

        // Asking the engine for price bounds brings back a distribution of every distinct price too.
        return [
            ...array_map(static fn (Facet $facet): FilterQuery => new FacetQuery($facet), $listing->facets()),
            ...$price instanceof PriceFilter ? [new PriceQuery($price)] : [],
            ...$sortQuery instanceof SortQuery ? [$sortQuery] : [],
        ];
    }

    public static function sortQuery(Listing $listing): ?SortQuery
    {
        $filters = SortFilter::carriedBy($listing->sorts());

        return $filters === [] ? null : new SortQuery($filters);
    }

    /**
     * @param  list<string>  $separatelyMeasuredKeys  the keys of the searches that measure a filter on their own
     * @return array<string, mixed>
     */
    public static function results(Listing $listing, ListingState $state, array $separatelyMeasuredKeys): array
    {
        $filters = self::filterQueries($listing);
        $scope = self::scope($listing, $state);
        $query = [
            'q' => self::searchTerm($listing, $state),
            'filter' => self::filter($scope, $state, $filters),
            'facets' => self::fieldsOnMain($filters, $separatelyMeasuredKeys),
            // `hitsPerPage`/`page` answer with `totalHits` and `totalPages`;
            // `limit`/`offset` only give an estimate, capped at maxTotalHits.
            'hitsPerPage' => $listing->perPage(),
            'page' => $state->page,
            'attributesToRetrieve' => [DocumentField::Card->value],
            ...self::searchedFields($scope),
        ];

        $sort = $listing->sorts()[$state->sort] ?? null;

        return $sort === null ? $query : [...$query, 'sort' => $sort->expressions];
    }

    /**
     * @return array<string, mixed>
     */
    public static function measureWithout(Listing $listing, ListingState $state, FilterQuery $lifted): array
    {
        $others = array_filter(
            self::filterQueries($listing),
            static fn (FilterQuery $filter): bool => $filter->key() !== $lifted->key()
        );

        $scope = self::scope($listing, $state);

        return [
            'q' => self::searchTerm($listing, $state),
            'filter' => self::filter($scope, $state, $others),
            'facets' => $lifted->fields(),
            'hitsPerPage' => self::NO_HIT,
            'page' => ListingState::FIRST_PAGE,
            ...self::searchedFields($scope),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function unfiltered(Listing $listing): array
    {
        $scope = self::scope($listing, new ListingState);

        return [
            'q' => $listing->baseQuery(),
            'filter' => FilterExpression::all($scope->filter),
            'facets' => array_map(static fn (Facet $facet): string => $facet->field(), $listing->facets()),
            'hitsPerPage' => self::NO_HIT,
            'page' => ListingState::FIRST_PAGE,
            ...self::searchedFields($scope),
        ];
    }

    private static function searchTerm(Listing $listing, ListingState $state): string
    {
        if (StateReader::isRoutedSearch($listing)) {
            return $listing->baseQuery();
        }

        return $state->query;
    }

    private static function scope(Listing $listing, ListingState $state): SearchScope
    {
        $searchTerm = self::searchTerm($listing, $state);

        return $searchTerm === '' ? SearchScope::browsing($listing) : SearchScope::searching($listing);
    }

    /**
     * @return array{attributesToSearchOn?: list<string>}
     */
    private static function searchedFields(SearchScope $scope): array
    {
        return $scope->fields === null ? [] : ['attributesToSearchOn' => $scope->fields];
    }

    /**
     * @param  array<FilterQuery>  $filters
     */
    private static function filter(SearchScope $scope, ListingState $state, array $filters): string
    {
        return FilterExpression::all([
            ...$scope->filter,
            ...array_map(static fn (FilterQuery $filter): string => $filter->clause($state), array_values($filters)),
        ]);
    }

    /**
     * @param  list<FilterQuery>  $filters
     * @param  list<string>  $separatelyMeasuredKeys
     * @return list<string>
     */
    private static function fieldsOnMain(array $filters, array $separatelyMeasuredKeys): array
    {
        $onMain = array_filter($filters, static fn (FilterQuery $filter): bool => ! in_array($filter->key(), $separatelyMeasuredKeys, true));

        return array_merge(...array_map(static fn (FilterQuery $filter): array => $filter->fields(), array_values($onMain)));
    }
}
