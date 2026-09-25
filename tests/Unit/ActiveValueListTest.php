<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\ActiveValueKind;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\View\ActiveValue;
use Modules\MeiliFacets\View\ActiveValueList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Standalone, `__()` hands back its key: the patterns below are the English ones. */
final class ActiveValueListTest extends TestCase
{
    private const array BRANDS = ['acme' => 5, 'globex' => 3, 'initech' => 2];

    #[Test]
    public function it_lists_the_ticked_values_in_facet_order_then_the_price(): void
    {
        $state = new ListingState(
            ['pa_size' => ['large'], 'product_brand' => ['acme', 'globex']],
            price: new Range(10.0, 50.0),
        );

        $this->assertSame(
            [['Acme', 'brand', 'acme'], ['Globex', 'brand', 'globex'], ['Large', 'size', 'large'], ['10.00 – 50.00', 'min_price', '']],
            $this->shapesOf($this->valuesFor($state)),
        );
    }

    /** The price pill says what it is: its parameter and empty value are no convention to read it by. */
    #[Test]
    public function it_tells_a_term_pill_from_the_price_pill(): void
    {
        $state = new ListingState(['product_brand' => ['acme']], price: new Range(10.0, 50.0));

        $kinds = array_map(static fn (ActiveValue $value): ActiveValueKind => $value->kind, $this->valuesFor($state));

        $this->assertSame([ActiveValueKind::Term, ActiveValueKind::Price], $kinds);
    }

    /** Past the visible ones, a value is folded away unless ticked: its pill still needs its words. */
    #[Test]
    public function it_names_a_value_ranked_among_the_folded_ones(): void
    {
        $values = $this->valuesFor(new ListingState(['product_brand' => ['initech']]));

        $this->assertSame([['Initech', 'brand', 'initech']], $this->shapesOf($values));
    }

    /** The client reads its words there once the visitor unfolds and ticks one. */
    #[Test]
    public function it_publishes_the_words_of_the_folded_values_too(): void
    {
        $listing = $this->listing(new ListingState, []);

        $this->assertSame(['acme' => 'Acme', 'globex' => 'Globex', 'initech' => 'Initech'], $listing->labelsOf($listing->facets()[0]));
    }

    #[Test]
    public function it_says_what_a_pill_does_with_the_label_in_it(): void
    {
        $values = $this->valuesFor(new ListingState(['product_brand' => ['acme']]));

        $this->assertSame('Remove the Acme filter', $values[0]->action);
    }

    #[Test]
    public function it_writes_a_range_held_by_one_bound(): void
    {
        $from = $this->valuesFor(new ListingState(price: new Range(10.0)));
        $upTo = $this->valuesFor(new ListingState(price: new Range(max: 50.0)));

        $this->assertSame('From 10.00', $from[0]->label);
        $this->assertSame('Up to 50.00', $upTo[0]->label);
    }

    /** Printed as the URL wrote it, the slug would let anyone write on the page. */
    #[Test]
    public function it_gives_no_pill_to_a_value_the_page_has_no_words_for(): void
    {
        $state = new ListingState(['product_brand' => ['<script>alert(1)</script>', 'acme', 'unknown']]);

        $this->assertSame([['Acme', 'brand', 'acme']], $this->shapesOf($this->valuesFor($state)));
    }

    /** `strtr()` fills in one pass: a label cannot bring in a placeholder of its own. */
    #[Test]
    public function it_fills_the_label_in_once(): void
    {
        $values = $this->valuesFor(new ListingState(['product_brand' => ['odd']]), ['odd' => ':label :max']);

        $this->assertSame('Remove the :label :max filter', $values[0]->action);
    }

    #[Test]
    public function it_lists_nothing_while_nothing_is_held(): void
    {
        $this->assertSame([], $this->valuesFor(new ListingState(sort: 'price_asc', query: 'coat')));
    }

    /**
     * @param  array<string, string>  $extraLabels
     * @return list<ActiveValue>
     */
    private function valuesFor(ListingState $state, array $extraLabels = []): array
    {
        return new ActiveValueList(new Money)->of($this->listing($state, $extraLabels));
    }

    /**
     * @param  array<string, string>  $extraLabels
     */
    private function listing(ListingState $state, array $extraLabels): ResolvedListing
    {
        $brands = self::BRANDS + array_fill_keys(array_keys($extraLabels), 1);
        $distribution = ['facets.product_brand' => $brands, 'facets.pa_size' => ['large' => 4]];
        $answer = ['facetDistribution' => $distribution];
        $labels = ['acme' => 'Acme', 'globex' => 'Globex', 'initech' => 'Initech', 'large' => 'Large', ...$extraLabels];

        return new ResolvedListing(
            new FakeListing([
                new Facet('product_brand', 'Brand', visible: 1, name: 'brand'),
                new Facet('pa_size', 'Size', name: 'size'),
                new PriceFilter('Price'),
            ]),
            $state,
            new ListingSearch(new FakeSearchEngine([
                'results' => $answer,
                'count:product_brand' => $answer,
                'count:pa_size' => $answer,
                'unfiltered' => $answer,
            ]), new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels($labels), new FakeTermScope, new FakeDefaultTerms),
            new UrlParameters(['product_brand' => 'brand', 'pa_size' => 'size']),
            new Unavailable,
            new EngineLimits(1000),
        );
    }

    /**
     * @param  list<ActiveValue>  $values
     * @return list<array{string, string, string}>
     */
    private function shapesOf(array $values): array
    {
        return array_map(static fn (ActiveValue $value): array => [$value->label, $value->parameter, $value->value], $values);
    }
}
