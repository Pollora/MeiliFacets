<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\CatalogOrderSetting;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogOrderSettingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function followableSettings(): iterable
    {
        yield 'no direction takes the default one' => ['date', ['post_date:desc']];
        yield 'explicit direction' => ['date-asc', ['post_date:asc']];
        yield 'price going down' => ['price-desc', ['price.max:desc']];
        yield 'written in capitals' => ['PRICE-DESC', ['price.max:desc']];
        yield 'unknown direction takes the default one' => ['date-sideways', ['post_date:desc']];
        yield 'empty direction takes the default one' => ['price-', ['price.min:asc']];
        yield 'anything after the direction is ignored' => ['price-desc-extra', ['price.max:desc']];
        yield 'relevance keeps the engine order' => ['relevance', []];
    }

    /**
     * @param  list<string>  $expressions
     */
    #[DataProvider('followableSettings')]
    #[Test]
    public function it_follows_the_setting_as_woocommerce_reads_it(string $setting, array $expressions): void
    {
        $order = CatalogOrderSetting::of($setting);

        $this->assertTrue($order->listingCanFollow());
        $this->assertSame($expressions, $order->expressions());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unfollowableSettings(): iterable
    {
        yield 'popularity' => ['popularity'];
        yield 'rating' => ['rating'];
        yield 'empty' => [''];
        yield 'direction alone' => ['-desc'];
        yield 'a known order glued to its direction' => ['pricedesc'];
    }

    #[DataProvider('unfollowableSettings')]
    #[Test]
    public function it_refuses_a_setting_it_cannot_follow_and_adds_no_sort(string $setting): void
    {
        $order = CatalogOrderSetting::of($setting);

        $this->assertFalse($order->listingCanFollow());
        $this->assertSame([], $order->expressions());
    }
}
