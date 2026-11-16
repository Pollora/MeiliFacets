<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ListingResults;

use Modules\MeiliFacets\Search\ListingResults;

/**
 * @param  list<array<string, mixed>>  $hits
 */
function resultsOf(array $hits): ListingResults
{
    return new ListingResults($hits, count($hits), []);
}

it('hands back each card with the id of its post', function (): void {
    $results = resultsOf([['ID' => 12, 'card' => ['title' => 'Soap']]]);

    expect($results->cards())->toBe([['title' => 'Soap', 'id' => 12]]);
});

/** A variation's card links to the product it belongs to. */
it('prefers the parent id over the document id', function (): void {
    $results = resultsOf([['ID' => 31, 'parent_id' => 30, 'card' => ['title' => 'Soap 50 ml']]]);

    expect($results->cards()[0]['id'])->toBe(30);
});

it('overrides an id the card already carries', function (): void {
    $results = resultsOf([['ID' => 12, 'card' => ['id' => 99, 'title' => 'Soap']]]);

    expect($results->cards()[0]['id'])->toBe(12);
});

it('leaves a card without id when the hit has none', function (): void {
    $results = resultsOf([['card' => ['title' => 'Soap']]]);

    expect($results->cards())->toBe([['title' => 'Soap']]);
});

it('leaves out a hit with no card or a card that is not an array', function (): void {
    $results = resultsOf([
        ['ID' => 1],
        ['ID' => 2, 'card' => 'Soap'],
        ['ID' => 3, 'card' => null],
        ['ID' => 4, 'card' => ['title' => 'Candle']],
    ]);

    expect($results->cards())->toBe([['title' => 'Candle', 'id' => 4]]);
});

it('hands back no card when there is no hit', function (): void {
    expect(resultsOf([])->cards())->toBe([]);
});

it('hands back the counts of a taxonomy and none for an unknown one', function (): void {
    $results = new ListingResults([], 0, ['product_brand' => ['acme' => 3]]);

    expect($results->distribution('product_brand'))->toBe(['acme' => 3]);
    expect($results->distribution('product_cat'))->toBe([]);
});

/** `null` means nothing narrowed the listing; an empty array means the taxonomy had no value left. */
it('tells an unfiltered taxonomy with no value from an unknown one', function (): void {
    $results = new ListingResults([], 0, [], unfilteredDistributions: ['product_brand' => ['acme' => 3], 'product_cat' => []]);

    expect($results->unfilteredDistribution('product_brand'))->toBe(['acme' => 3]);
    expect($results->unfilteredDistribution('product_cat'))->toBe([]);
    expect($results->unfilteredDistribution('product_tag'))->toBeNull();
});
