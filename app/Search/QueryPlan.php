<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;

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
            ]),
            'facets' => self::fieldsCountedOnMain($listing, $state),
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
