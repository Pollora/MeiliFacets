<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\PriceQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceQueryTest extends TestCase
{
    #[Test]
    public function it_measures_the_price_separately_only_once_a_range_is_held(): void
    {
        $price = new PriceQuery(new PriceFilter('Price'));

        $this->assertFalse($price->isMeasuredSeparately(new ListingState));
        $this->assertTrue($price->isMeasuredSeparately(new ListingState(price: new Range(max: 120.0))));
    }
}
