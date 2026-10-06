<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Generator;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\FilterExpression;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The server half of `tests/variant-plan-cases.json`; `tests/ts/listing-query.test.ts` plans the same states
 * in the browser.
 *
 * @phpstan-type PlanCase array{
 *     case: string,
 *     state: array{facets: array<string, list<string>>, sort: ?string, query: string, price: array{min: ?float, max: ?float}},
 *     plan: array<string, array<string, mixed>>
 * }
 */
final class VariantPlanCasesTest extends TestCase
{
    private const string CASES = __DIR__.'/../variant-plan-cases.json';

    private const string UNFILTERED = 'unfiltered';

    private const array COMPARED = ['q', 'filter', 'facets', 'distinct', 'sort', 'attributesToSearchOn'];

    /**
     * @return Generator<string, array{PlanCase}>
     */
    public static function cases(): Generator
    {
        foreach (self::shared()['cases'] as $case) {
            yield $case['case'] => [$case];
        }
    }

    /**
     * @param  PlanCase  $case
     */
    #[DataProvider('cases')]
    #[Test]
    public function it_plans_the_searches_the_browser_plans(array $case): void
    {
        $engine = new FakeSearchEngine;
        $state = $case['state'];
        $price = new Range($state['price']['min'], $state['price']['max']);

        new ListingSearch($engine, new DisjunctiveFacetCounter)
            ->run(new FakeVariantScopedListing, new ListingState($state['facets'], $state['sort'], query: $state['query'], price: $price));

        $this->assertSame($case['plan'], $this->compared($engine->received[0]));
    }

    #[Test]
    public function it_describes_the_listing_the_cases_were_written_for(): void
    {
        $listing = new FakeVariantScopedListing;
        $described = self::shared()['listing'];

        $this->assertSame($described['filter'], FilterExpression::all($listing->baseFilter()));
        $this->assertSame($described['variantResults']['filter'], FilterExpression::all($listing->variantFilter()));
        $this->assertSame($described['variantResults']['taxonomies'], $listing->variantTaxonomies());
    }

    /**
     * @param  array<string, array<string, mixed>>  $searches
     * @return array<string, array<string, mixed>>
     */
    private function compared(array $searches): array
    {
        unset($searches[self::UNFILTERED]);

        return array_map(
            static fn (array $search): array => array_filter(
                [...array_fill_keys(self::COMPARED, null), 'q' => '', 'filter' => '', 'facets' => [], ...array_intersect_key($search, array_flip(self::COMPARED))],
                static fn (mixed $value): bool => $value !== null,
            ),
            $searches,
        );
    }

    /**
     * @return array{listing: array<string, mixed>, cases: list<PlanCase>}
     */
    private static function shared(): array
    {
        return json_decode((string) file_get_contents(self::CASES), true, flags: JSON_THROW_ON_ERROR);
    }
}
