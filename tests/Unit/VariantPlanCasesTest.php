<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\VariantPlanCases;

use Modules\MeiliFacets\Listing\FilterExpression;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;

// The server half of `tests/variant-plan-cases.json`; `tests/ts/listing-query.test.ts` plans the same states
// in the browser.
//
// @phpstan-type PlanCase array{
//     case: string,
//     state: array{facets: array<string, list<string>>, sort: ?string, query: string, price: array{min: ?float, max: ?float}},
//     plan: array<string, array<string, mixed>>
// }

const CASES = __DIR__.'/../variant-plan-cases.json';

const UNFILTERED = 'unfiltered';

const UNFILTERED_VARIANTS = 'unfiltered:variants';

const COMPARED = ['q', 'filter', 'facets', 'distinct', 'sort', 'attributesToSearchOn'];

/**
 * @param  array<string, array<string, mixed>>  $searches
 * @return array<string, array<string, mixed>>
 */
function compared(array $searches): array
{
    unset($searches[UNFILTERED], $searches[UNFILTERED_VARIANTS]);

    return array_map(
        static fn (array $search): array => array_filter(
            [...array_fill_keys(COMPARED, null), 'q' => '', 'filter' => '', 'facets' => [], ...array_intersect_key($search, array_flip(COMPARED))],
            static fn (mixed $value): bool => $value !== null,
        ),
        $searches,
    );
}

/**
 * @return array{listing: array<string, mixed>, cases: list<PlanCase>}
 */
function shared(): array
{
    return json_decode((string) file_get_contents(CASES), true, flags: JSON_THROW_ON_ERROR);
}

it('plans the searches the browser plans', function (array $case): void {
    $engine = new FakeSearchEngine;
    $state = $case['state'];
    $price = new Range($state['price']['min'], $state['price']['max']);

    new ListingSearch($engine, new DisjunctiveFacetCounter)
        ->run(new FakeVariantScopedListing, new ListingState($state['facets'], $state['sort'], query: $state['query'], price: $price));

    expect(compared($engine->received[0]))->toBe($case['plan']);
})->with(function () {
    foreach (shared()['cases'] as $case) {
        yield $case['case'] => [$case];
    }
});

it('describes the listing the cases were written for', function (): void {
    $listing = new FakeVariantScopedListing;
    $described = shared()['listing'];

    expect(FilterExpression::all($listing->baseFilter()))->toBe($described['filter']);
    expect(FilterExpression::all($listing->variantFilter()))->toBe($described['variantResults']['filter']);
    expect($listing->variantTaxonomies())->toBe($described['variantResults']['taxonomies']);
    expect($listing->defaultSort())->toBe($described['defaultSort']);
    expect(QueryPlan::variantSort($listing->defaultSort()))->toBe($described['variantResults']['defaultSort']);
});
