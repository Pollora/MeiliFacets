<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\StateReader;

use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;

beforeEach(function (): void {
    $this->reader = new StateReader(new UrlParameters(['product_brand' => 'brand']));
    $this->listing = FakeListing::withBrandAndCategory();
});

/** JSON writes `0` for a bound the state holds as `0.0`. */
function bound(int|float|null $bound): ?float
{
    return $bound === null ? null : (float) $bound;
}

function sharedListing(): FakeListing
{
    return new FakeListing([
        new Facet('product_brand', 'Brand'),
        new Facet('product_cat', 'Category', SelectionMode::Single),
        new PriceFilter('Price'),
    ]);
}

it('reads a mapped parameter under its configured name', function (): void {
    $state = $this->reader->read($this->listing, ['brand' => 'acme']);

    expect($state->selected('product_brand'))->toBe(['acme']);
});

it('reads an unmapped taxonomy under its prefixed name', function (): void {
    $state = $this->reader->read($this->listing, ['f_product_cat' => 'coats']);

    expect($state->selected('product_cat'))->toBe(['coats']);
});

it('ignores a bare taxonomy name', function (): void {
    $state = $this->reader->read($this->listing, ['product_cat' => 'coats']);

    expect($state->selected('product_cat'))->toBe([]);
});

it('reads the comma form only', function (): void {
    $comma = $this->reader->read($this->listing, ['brand' => 'acme,globex']);
    $array = $this->reader->read($this->listing, ['brand' => ['acme', 'globex']]);

    expect($comma->selected('product_brand'))->toBe(['acme', 'globex']);
    expect($array->selected('product_brand'))->toBe([]);
});

/** A sort the listing does not declare must not create a page of its own. */
it('drops a sort the listing does not declare', function (): void {
    expect($this->reader->read($this->listing, ['sort' => 'price_asc'])->sort)->toBe('price_asc');
    expect($this->reader->read($this->listing, ['sort' => 'made_up'])->sort)->toBeNull();
});

/** One state, one URL: values are sorted so Varnish caches it once. */
it('sorts the values it reads', function (): void {
    expect($this->reader->read($this->listing, ['brand' => 'globex,acme'])->selected('product_brand'))->toBe(['acme', 'globex']);
});

it('drops a value repeated in the url', function (): void {
    expect($this->reader->read($this->listing, ['brand' => 'acme,acme'])->selected('product_brand'))->toBe(['acme']);
});

/** A crafted URL must not turn into thousands of filter clauses. */
it('caps the number of values a facet can hold', function (): void {
    $many = implode(',', array_map(static fn (int $i): string => 'v'.$i, range(1, 500)));

    expect($this->reader->read($this->listing, ['brand' => $many])->selected('product_brand'))->toHaveCount(30);
});

it('caps the length of the search term', function (): void {
    expect(mb_strlen($this->reader->read($this->listing, ['q' => str_repeat('a', 5000)])->query))->toBe(200);
});

/** R-159: the engine tokenises such a term into nothing and serves the whole index. */
it('reads no term from one holding no letter and no figure', function (): void {
    expect($this->reader->read($this->listing, ['q' => '?('])->query)->toBe('');
    expect($this->reader->read($this->listing, ['q' => "\xff"])->query)->toBe('');
    expect($this->reader->read($this->listing, ['q' => ' é '])->query)->toBe('é');
    expect($this->reader->read($this->listing, ['q' => '4'])->query)->toBe('4');
});

/** No field of the listing can take a term off a search WordPress routed. */
it('ignores the term of the url on a search wordpress routed', function (): void {
    $routed = new FakeListing(baseQuery: 'creme');

    expect($this->reader->read($routed, ['q' => 'zzzz'])->query)->toBe('');
});

it('keeps a single value for a single selection facet', function (): void {
    $state = $this->reader->read($this->listing, ['f_product_cat' => 'shirts,coats']);

    expect($state->selected('product_cat'))->toBe(['coats']);
});

it('drops empty and blank values', function (): void {
    $state = $this->reader->read($this->listing, ['brand' => 'acme,, ,globex']);

    expect($state->selected('product_brand'))->toBe(['acme', 'globex']);
});

it('survives a parameter sent as a nested array', function (): void {
    $state = $this->reader->read($this->listing, ['brand' => [['acme']], 'sort' => ['x']]);

    expect($state->selected('product_brand'))->toBe([]);
    expect($state->sort)->toBeNull();
});

/** The path a visitor followed is merged in as `pg`, so an explicit one has to win. */
it('reads the page from the parameter it was given', function (): void {
    $state = $this->reader->read($this->listing, ['pg' => '3']);

    expect($state->page)->toBe(3);
});

it('falls back to the first page', function (): void {
    foreach (['', 'abc', '0', '-3'] as $raw) {
        expect($this->reader->read($this->listing, ['pg' => $raw])->page)->toBe(ListingState::FIRST_PAGE);
    }

    expect($this->reader->read($this->listing, ['pg' => '3'])->page)->toBe(3);
});

it('counts the filters a visitor has applied', function (): void {
    $state = $this->reader->read($this->listing, ['brand' => 'acme,globex', 'f_product_cat' => 'coats']);

    expect($state->activeFilterCount())->toBe(3);
    expect($state->isPristine())->toBeFalse();
});

it('reads no range for a listing that declares none', function (): void {
    $state = $this->reader->read(FakeListing::withBrandAndCategory(), ['min_price' => '20', 'max_price' => '60']);

    expect($state->price->isEmpty())->toBeTrue();
    expect($state->isPristine())->toBeTrue();
    expect($state->activeFilterCount())->toBe(0);
});

it('reads the range a listing declares', function (): void {
    $state = $this->reader->read(FakeListing::withPriceAndBrand(), ['min_price' => '20', 'max_price' => '60']);

    expect($state->price->min)->toEqualWithDelta(20.0, PHP_FLOAT_EPSILON);
    expect($state->price->max)->toEqualWithDelta(60.0, PHP_FLOAT_EPSILON);
});

it('counts a price range as one filter whatever its ends', function (): void {
    expect(new ListingState(price: new Range(20.0, 60.0))->activeFilterCount())->toBe(1);
    expect(new ListingState(price: new Range(max: 60.0))->activeFilterCount())->toBe(1);
    expect(new ListingState(['brand' => ['acme', 'globex']], price: new Range(min: 20.0))->activeFilterCount())->toBe(3);
});

it('reports an untouched listing as pristine', function (): void {
    expect($this->reader->read($this->listing, [])->isPristine())->toBeTrue();
});

it('drops a value that is not utf8', function (): void {
    $state = $this->reader->read($this->listing, ['brand' => "acme,\xFF"]);

    expect($state->selected('product_brand'))->toBe(['acme']);
});

it('keeps a value as long as a slug can be', function (): void {
    $longest = str_repeat('a', StateReader::MAX_VALUE_BYTES);

    expect($this->reader->read($this->listing, ['brand' => $longest])->selected('product_brand'))->toBe([$longest]);
});

it('drops a value longer than a slug can be', function (): void {
    $state = $this->reader->read($this->listing, ['brand' => 'acme,'.str_repeat('a', StateReader::MAX_VALUE_BYTES + 1)]);

    expect($state->selected('product_brand'))->toBe(['acme']);
});

it('reads a url written by hand', function (string $url, array $facets, ?string $sort, int $page, string $text, array $price): void {
    parse_str($url, $query);

    $state = $this->reader->read(sharedListing(), $query);

    $this->assertSame($facets, $state->facets, 'facets');
    $this->assertSame($sort, $state->sort, 'sort');
    $this->assertSame($page, $state->page, 'page');
    $this->assertSame($text, $state->query, 'query');
    $this->assertSame($price, [$state->price->min, $state->price->max], 'price');
})->with(function () {
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
});

/** `tests/ts/listing-url.test.ts` checks the client writes each `search` from its `state`. */
it('reads back the state the client wrote', function (array $written): void {
    parse_str(ltrim($written['search'], '?'), $query);

    $state = $this->reader->read(sharedListing(), $query);
    $expected = $written['state'];

    $this->assertSame($expected['facets'], $state->facets, 'facets');
    $this->assertSame($expected['query'], $state->query, 'query');
    $this->assertSame($expected['sort'], $state->sort, 'sort');
    $this->assertSame($expected['page'], $state->page, 'page');
    $this->assertSame(
        [bound($expected['price']['min']), bound($expected['price']['max'])],
        [$state->price->min, $state->price->max],
        'price'
    );
})->with(function () {
    $cases = json_decode((string) file_get_contents(__DIR__.'/../url-writing-cases.json'), true, flags: JSON_THROW_ON_ERROR);

    foreach ($cases as $case) {
        yield $case['case'] => [$case];
    }
});
