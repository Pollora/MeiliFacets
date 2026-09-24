<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StateReaderTest extends TestCase
{
    private StateReader $reader;

    private FakeListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new StateReader(new UrlParameters(['product_brand' => 'brand']));
        $this->listing = FakeListing::withBrandAndCategory();
    }

    #[Test]
    public function it_reads_a_mapped_parameter_under_its_configured_name(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => 'acme']);

        $this->assertSame(['acme'], $state->selected('product_brand'));
    }

    #[Test]
    public function it_reads_an_unmapped_taxonomy_under_its_prefixed_name(): void
    {
        $state = $this->reader->read($this->listing, ['f_product_cat' => 'coats']);

        $this->assertSame(['coats'], $state->selected('product_cat'));
    }

    #[Test]
    public function it_ignores_a_bare_taxonomy_name(): void
    {
        $state = $this->reader->read($this->listing, ['product_cat' => 'coats']);

        $this->assertSame([], $state->selected('product_cat'));
    }

    // One state, one URL: an array form would give Varnish a second cache entry.
    #[Test]
    public function it_reads_the_comma_form_only(): void
    {
        $comma = $this->reader->read($this->listing, ['brand' => 'acme,globex']);
        $array = $this->reader->read($this->listing, ['brand' => ['acme', 'globex']]);

        $this->assertSame(['acme', 'globex'], $comma->selected('product_brand'));
        $this->assertSame([], $array->selected('product_brand'));
    }

    /** A sort the listing does not declare must not create a page of its own. */
    #[Test]
    public function it_drops_a_sort_the_listing_does_not_declare(): void
    {
        $this->assertSame('price_asc', $this->reader->read($this->listing, ['sort' => 'price_asc'])->sort);
        $this->assertNull($this->reader->read($this->listing, ['sort' => 'made_up'])->sort);
    }

    /** One state, one URL: values are sorted so Varnish caches it once. */
    #[Test]
    public function it_sorts_the_values_it_reads(): void
    {
        $this->assertSame(
            ['acme', 'globex'],
            $this->reader->read($this->listing, ['brand' => 'globex,acme'])->selected('product_brand')
        );
    }

    #[Test]
    public function it_drops_a_value_repeated_in_the_url(): void
    {
        $this->assertSame(
            ['acme'],
            $this->reader->read($this->listing, ['brand' => 'acme,acme'])->selected('product_brand')
        );
    }

    /** A crafted URL must not turn into thousands of filter clauses. */
    #[Test]
    public function it_caps_the_number_of_values_a_facet_can_hold(): void
    {
        $many = implode(',', array_map(static fn (int $i): string => 'v'.$i, range(1, 500)));

        $this->assertCount(30, $this->reader->read($this->listing, ['brand' => $many])->selected('product_brand'));
    }

    #[Test]
    public function it_caps_the_length_of_the_search_term(): void
    {
        $this->assertSame(200, mb_strlen($this->reader->read($this->listing, ['q' => str_repeat('a', 5000)])->query));
    }

    #[Test]
    public function it_keeps_a_single_value_for_a_single_selection_facet(): void
    {
        $state = $this->reader->read($this->listing, ['f_product_cat' => 'shirts,coats']);

        $this->assertSame(['coats'], $state->selected('product_cat'));
    }

    #[Test]
    public function it_drops_empty_and_blank_values(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => 'acme,, ,globex']);

        $this->assertSame(['acme', 'globex'], $state->selected('product_brand'));
    }

    #[Test]
    public function it_survives_a_parameter_sent_as_a_nested_array(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => [['acme']], 'sort' => ['x']]);

        $this->assertSame([], $state->selected('product_brand'));
        $this->assertNull($state->sort);
    }

    /** The path a visitor followed is merged in as `pg`, so an explicit one has to win. */
    #[Test]
    public function it_reads_the_page_from_the_parameter_it_was_given(): void
    {
        $state = $this->reader->read($this->listing, ['pg' => '3']);

        $this->assertSame(3, $state->page);
    }

    #[Test]
    public function it_falls_back_to_the_first_page(): void
    {
        foreach (['', 'abc', '0', '-3'] as $raw) {
            $this->assertSame(ListingState::FIRST_PAGE, $this->reader->read($this->listing, ['pg' => $raw])->page);
        }

        $this->assertSame(3, $this->reader->read($this->listing, ['pg' => '3'])->page);
    }

    #[Test]
    public function it_counts_the_filters_a_visitor_has_applied(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => 'acme,globex', 'f_product_cat' => 'coats']);

        $this->assertSame(3, $state->activeFilterCount());
        $this->assertFalse($state->isPristine());
    }

    #[Test]
    public function it_reads_no_range_for_a_listing_that_declares_none(): void
    {
        $state = $this->reader->read(FakeListing::withBrandAndCategory(), ['min_price' => '20', 'max_price' => '60']);

        $this->assertTrue($state->price->isEmpty());
        $this->assertTrue($state->isPristine());
        $this->assertSame(0, $state->activeFilterCount());
    }

    #[Test]
    public function it_reads_the_range_a_listing_declares(): void
    {
        $state = $this->reader->read(FakeListing::withPriceAndBrand(), ['min_price' => '20', 'max_price' => '60']);

        $this->assertEqualsWithDelta(20.0, $state->price->min, PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(60.0, $state->price->max, PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function it_counts_a_price_range_as_one_filter_whatever_its_ends(): void
    {
        $this->assertSame(1, new ListingState(price: new Range(20.0, 60.0))->activeFilterCount());
        $this->assertSame(1, new ListingState(price: new Range(max: 60.0))->activeFilterCount());
        $this->assertSame(3, new ListingState(['brand' => ['acme', 'globex']], price: new Range(min: 20.0))->activeFilterCount());
    }

    #[Test]
    public function it_reports_an_untouched_listing_as_pristine(): void
    {
        $this->assertTrue($this->reader->read($this->listing, [])->isPristine());
    }

    #[Test]
    public function it_drops_a_value_that_is_not_utf8(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => "acme,\xFF"]);

        $this->assertSame(['acme'], $state->selected('product_brand'));
    }

    /**
     * @param  array<string, list<string>>  $facets
     * @param  array{?float, ?float}  $price
     */
    #[DataProvider('handWrittenUrls')]
    #[Test]
    public function it_reads_a_url_written_by_hand(string $url, array $facets, ?string $sort, int $page, string $text, array $price): void
    {
        parse_str($url, $query);

        $state = $this->reader->read($this->sharedListing(), $query);

        $this->assertSame($facets, $state->facets, 'facets');
        $this->assertSame($sort, $state->sort, 'sort');
        $this->assertSame($page, $state->page, 'page');
        $this->assertSame($text, $state->query, 'query');
        $this->assertSame($price, [$state->price->min, $state->price->max], 'price');
    }

    /**
     * @return iterable<string, array{string, array<string, list<string>>, ?string, int, string, array{?float, ?float}}>
     */
    public static function handWrittenUrls(): iterable
    {
        yield 'values of a facet, sorted and deduplicated' => ['brand=globex,acme,acme,%20,', ['product_brand' => ['acme', 'globex']], null, 1, '', [null, null]];
        yield 'a no-break space is not trimmed' => ['brand=%C2%A0acme', ['product_brand' => ["\u{A0}acme"]], null, 1, '', [null, null]];
        yield 'values sorted byte by byte before a single choice keeps one' => ['f_product_cat=9,10', ['product_cat' => ['10']], null, 1, '', [null, null]];
        yield 'a repeated parameter keeps its last value' => ['brand=zz&brand=acme', ['product_brand' => ['acme']], null, 1, '', [null, null]];
        yield 'the array form is not read' => ['brand[]=acme', [], null, 1, '', [null, null]];
        yield 'a sort padded with spaces' => ['sort=%20price_asc%09', [], 'price_asc', 1, '', [null, null]];
        yield 'a sort the listing does not declare' => ['sort=made_up', [], null, 1, '', [null, null]];
        yield 'a page written with an exponent' => ['pg=1e3', [], null, 1000, '', [null, null]];
        yield 'a page followed by letters' => ['pg=3abc', [], null, 3, '', [null, null]];
        yield 'a page behind a no-break space' => ['pg=%C2%A03', [], null, 1, '', [null, null]];
        yield 'a page below the first' => ['pg=-4', [], null, 1, '', [null, null]];
        yield 'a query cut from NUL but not from a no-break space' => ['q=%00coat%C2%A0', [], null, 1, "coat\u{A0}", [null, null]];
        yield 'prices written as decimals and exponents' => ['min_price=.5&max_price=1e3', [], null, 1, '', [0.5, 1000.0]];
        yield 'prices PHP does not call numeric' => ['min_price=12abc&max_price=12,5', [], null, 1, '', [null, null]];
        yield 'a hexadecimal price' => ['min_price=0x1A', [], null, 1, '', [null, null]];
        yield 'a negative price and a negative zero' => ['min_price=-5&max_price=-0', [], null, 1, '', [null, 0.0]];
        yield 'a price behind a no-break space' => ['min_price=%C2%A012', [], null, 1, '', [null, null]];
        yield 'an infinite price' => ['max_price=1e400', [], null, 1, '', [null, null]];
    }

    /**
     * `tests/ts/listing-url.test.ts` checks the client writes each `search` from its `state`.
     *
     * @param  array{case: string, search: string, state: array{facets: array<string, list<string>>, query: string, sort: ?string, page: int, price: array{min: int|float|null, max: int|float|null}}}  $written
     */
    #[DataProvider('urlsTheClientWrites')]
    #[Test]
    public function it_reads_back_the_state_the_client_wrote(array $written): void
    {
        parse_str(ltrim($written['search'], '?'), $query);

        $state = $this->reader->read($this->sharedListing(), $query);
        $expected = $written['state'];

        $this->assertSame($expected['facets'], $state->facets, 'facets');
        $this->assertSame($expected['query'], $state->query, 'query');
        $this->assertSame($expected['sort'], $state->sort, 'sort');
        $this->assertSame($expected['page'], $state->page, 'page');
        $this->assertSame(
            [$this->bound($expected['price']['min']), $this->bound($expected['price']['max'])],
            [$state->price->min, $state->price->max],
            'price'
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function urlsTheClientWrites(): iterable
    {
        $cases = json_decode((string) file_get_contents(__DIR__.'/../url-writing-cases.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($cases as $case) {
            yield $case['case'] => [$case];
        }
    }

    /** JSON writes `0` for a bound the state holds as `0.0`. */
    private function bound(int|float|null $bound): ?float
    {
        return $bound === null ? null : (float) $bound;
    }

    private function sharedListing(): FakeListing
    {
        return new FakeListing([
            new Facet('product_brand', 'Brand'),
            new Facet('product_cat', 'Category', SelectionMode::Single),
            new PriceFilter('Price'),
        ]);
    }
}
