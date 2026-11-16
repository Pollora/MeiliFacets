<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\PriceQuery;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\PriceQuery;

it('measures the price separately only once a range is held', function (): void {
    $price = new PriceQuery(new PriceFilter('Price'));

    expect($price->isMeasuredSeparately(new ListingState))->toBeFalse();
    expect($price->isMeasuredSeparately(new ListingState(price: new Range(max: 120.0))))->toBeTrue();
});
