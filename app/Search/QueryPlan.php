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
     * @return array<string, mixed>
     */
    public static function results(Listing $listing, ListingState $state): array
    {
        $query = [
            'q' => $state->query,
            'filter' => FilterExpression::all([
                ...$listing->baseFilter(),
                ...self::facetClauses($listing, $state),
                self::priceClause($listing, $state),
            ]),
            'facets' => [
                ...self::fieldsCountedOnMain($listing, $state),
                ...self::measuresPriceApart($listing, $state) ? [] : self::priceFields($listing),
            ],
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
     * The range must reach what the visitor could still choose, so its own
     * constraint is lifted — the same reason a facet is counted with its own values
     * freed. Without this, filtering narrows it with no way back.
     *
     * @return array<string, mixed>
     */
    public static function priceBounds(Listing $listing, ListingState $state): array
    {
        return [
            'q' => $state->query,
            'filter' => FilterExpression::all([
                ...$listing->baseFilter(),
                ...self::facetClauses($listing, $state),
            ]),
            'facets' => self::priceFields($listing),
            'hitsPerPage' => self::NO_HIT,
            'page' => ListingState::FIRST_PAGE,
        ];
    }

    /** Lifting the price costs a search, so it is only asked when a range is held. */
    public static function measuresPriceApart(Listing $listing, ListingState $state): bool
    {
        return ! $state->price->isEmpty() && self::priceFields($listing) !== [];
    }

    /** A listing that declares no range must not be filtered by one the URL happens to carry. */
    private static function priceClause(Listing $listing, ListingState $state): string
    {
        return self::priceFields($listing) === []
            ? ''
            : FilterExpression::overlapping(PriceTax::excluding($state->price));
    }

    /**
     * Only a listing that declares a price range pays for its bounds: asking the
     * engine for them brings back a distribution of every distinct price too.
     *
     * @return list<string>
     */
    private static function priceFields(Listing $listing): array
    {
        return PriceFilter::among($listing->filters())?->fields() ?? [];
    }

    /**
     * Counts a facet as if its own constraint were lifted, so its other values
     * stay reachable.
     *
     * @return array<string, mixed>
     */
    public static function counting(Listing $listing, ListingState $state, Facet $counted): array
    {
        return [
            'q' => $state->query,
            'filter' => FilterExpression::all([
                ...$listing->baseFilter(),
                ...self::facetClauses($listing, $state, $counted->taxonomy),
                self::priceClause($listing, $state),
            ]),
            'facets' => [$counted->field()],
            'hitsPerPage' => self::NO_HIT,
            'page' => ListingState::FIRST_PAGE,
        ];
    }

    /**
     * A facet only needs a search of its own once it constrains the results;
     * until then the main response counts it correctly.
     */
    public static function isCountedApart(Facet $facet, ListingState $state): bool
    {
        return $facet->needsDisjunctiveCount() && $state->selected($facet->taxonomy) !== [];
    }

    /**
     * @return list<string>
     */
    private static function fieldsCountedOnMain(Listing $listing, ListingState $state): array
    {
        $onMain = array_filter(
            $listing->facets(),
            static fn (Facet $facet): bool => ! self::isCountedApart($facet, $state)
        );

        return array_values(array_map(static fn (Facet $facet): string => $facet->field(), $onMain));
    }

    /**
     * @return list<string>
     */
    private static function facetClauses(Listing $listing, ListingState $state, ?string $except = null): array
    {
        $clauses = [];

        foreach ($listing->facets() as $facet) {
            if ($facet->taxonomy !== $except) {
                $clauses[] = FilterExpression::facet($facet, $state->selected($facet->taxonomy));
            }
        }

        return array_values(array_filter($clauses, strlen(...)));
    }
}
