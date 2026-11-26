<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\PricePill;

use Modules\MeiliFacets\Enums\ActiveValueKind;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\ActiveValueList;

beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    WordPress::fake('wc_price', static fn (float $value): string => number_format($value, 2));
    WordPress::fake('wp_strip_all_tags', strip_tags(...));
});

it('offers to take the price off after the search term', function (): void {
    $resolved = new ResolvedListing(
        new FakeListing,
        new ListingState(query: 'wine', price: new Range(10.0)),
        new ListingSearch(new FakeSearchEngine, new DisjunctiveFacetCounter),
        new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
        new EngineLimits(1000),
    );

    $pills = new ActiveValueList(new Money, new UrlParameters)->of($resolved);

    expect($pills)->toHaveCount(2)
        ->and($pills[1]->label)->toBe('From 10.00')
        ->and($pills[1]->parameter)->toBe('min_price')
        ->and($pills[1]->value)->toBe('')
        ->and($pills[1]->action)->toBe('Remove the From 10.00 filter')
        ->and($pills[1]->kind)->toBe(ActiveValueKind::Price);
});
