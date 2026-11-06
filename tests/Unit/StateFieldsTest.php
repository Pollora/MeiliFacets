<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\View\HiddenField;
use Modules\MeiliFacets\View\StateFields;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** What a search form sends again of the state, so that searching keeps the filters and the order in force. */
final class StateFieldsTest extends TestCase
{
    #[Test]
    public function it_sends_nothing_while_nothing_is_held(): void
    {
        $this->assertSame([], $this->fieldsOf(new ListingState));
    }

    #[Test]
    public function it_sends_the_values_of_a_facet_in_one_field_under_its_url_parameter(): void
    {
        $fields = $this->fieldsOf(new ListingState(['product_brand' => ['acme', 'globex']]));

        $this->assertSame([['brand', 'acme,globex']], $this->pairsOf($fields));
    }

    /** An unmapped taxonomy takes the prefixed name: its bare name is a WordPress query var. */
    #[Test]
    public function it_names_an_unmapped_taxonomy_by_its_prefixed_parameter(): void
    {
        $fields = $this->fieldsOf(new ListingState(['pa_size' => ['large']]));

        $this->assertSame([[UrlParameters::UNMAPPED_PREFIX.'pa_size', 'large']], $this->pairsOf($fields));
    }

    #[Test]
    public function it_leaves_out_a_facet_with_nothing_ticked(): void
    {
        $fields = $this->fieldsOf(new ListingState(['product_brand' => [], 'pa_size' => ['large']]));

        $this->assertSame([[UrlParameters::UNMAPPED_PREFIX.'pa_size', 'large']], $this->pairsOf($fields));
    }

    /** The state may name a taxonomy the listing does not filter on: the URL wrote it, the form must not repeat it. */
    #[Test]
    public function it_leaves_out_a_taxonomy_the_listing_has_no_facet_for(): void
    {
        $this->assertSame([], $this->fieldsOf(new ListingState(['product_tag' => ['sale']])));
    }

    #[Test]
    public function it_sends_the_sort_in_force_under_its_reserved_parameter(): void
    {
        $fields = $this->fieldsOf(new ListingState(sort: 'price_asc'), ['sort' => 'tri']);

        $this->assertSame([['tri', 'price_asc']], $this->pairsOf($fields));
    }

    #[Test]
    public function it_sends_only_the_price_bound_that_was_asked(): void
    {
        $this->assertSame([['min_price', '10']], $this->pairsOf($this->fieldsOf(new ListingState(price: new Range(10.0)))));
        $this->assertSame([['max_price', '50']], $this->pairsOf($this->fieldsOf(new ListingState(price: new Range(max: 50.0)))));
    }

    /** A bound is written as the URL would write it: no trailing zeros, no exponent. */
    #[Test]
    public function it_writes_a_price_bound_without_trailing_zeros(): void
    {
        $fields = $this->fieldsOf(new ListingState(price: new Range(0.0000001, 10.5)));

        $this->assertSame([['min_price', '0'], ['max_price', '10.5']], $this->pairsOf($fields));
    }

    /** The searched term and the page are left out: a new search starts from its first page, with its own term. */
    #[Test]
    public function it_sends_the_facets_then_the_sort_then_the_price_and_neither_the_term_nor_the_page(): void
    {
        $state = new ListingState(
            ['pa_size' => ['large'], 'product_brand' => ['acme']],
            sort: 'newest',
            page: 3,
            query: 'coat',
            price: new Range(10.0, 50.0),
        );

        $this->assertSame([
            ['brand', 'acme'],
            [UrlParameters::UNMAPPED_PREFIX.'pa_size', 'large'],
            ['sort', 'newest'],
            ['min_price', '10'],
            ['max_price', '50'],
        ], $this->pairsOf($this->fieldsOf($state)));
    }

    /**
     * @param  array<string, string>  $reserved
     * @return list<HiddenField>
     */
    private function fieldsOf(ListingState $state, array $reserved = []): array
    {
        return new StateFields(new UrlParameters(['product_brand' => 'brand'], $reserved))->of(new ResolvedListing(
            new FakeListing([
                new Facet('product_brand', 'Brand', name: 'brand'),
                new PriceFilter('Price'),
                new Facet('pa_size', 'Size', name: 'size'),
            ]),
            $state,
            new ListingSearch(new FakeSearchEngine, new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new EngineLimits(1000),
        ));
    }

    /**
     * @param  list<HiddenField>  $fields
     * @return list<array{string, string}>
     */
    private function pairsOf(array $fields): array
    {
        return array_map(static fn (HiddenField $field): array => [$field->name, $field->value], $fields);
    }
}
