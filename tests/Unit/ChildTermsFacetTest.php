<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChildTermsFacetTest extends TestCase
{
    private const array DISTRIBUTION = [
        'visage' => 30, 'serums' => 9, 'cremes-visage' => 7,
        'cheveux' => 15, 'shampoings' => 6,
    ];

    #[Test]
    public function it_offers_the_level_the_visitor_is_on(): void
    {
        $scope = new FakeTermScope(['product_cat' => ['serums', 'cremes-visage']]);

        $kept = $this->facet()->within(self::DISTRIBUTION, $scope);

        $this->assertSame(['serums' => 9, 'cremes-visage' => 7], $kept);
    }

    /** Moving sideways belongs to a breadcrumb, not to a control that narrows. */
    #[Test]
    public function it_offers_nothing_on_a_term_without_children(): void
    {
        $this->assertSame([], $this->facet()->within(self::DISTRIBUTION, new FakeTermScope));
    }

    /** A child the engine counted nowhere is not invented. */
    #[Test]
    public function it_keeps_only_the_children_the_engine_counted(): void
    {
        $scope = new FakeTermScope(['product_cat' => ['serums', 'masques']]);

        $this->assertSame(['serums' => 9], $this->facet()->within(self::DISTRIBUTION, $scope));
    }

    #[Test]
    public function a_plain_facet_shows_everything_the_engine_returned(): void
    {
        $facet = new Facet('product_brand', 'Brand');

        $this->assertSame(self::DISTRIBUTION, $facet->within(self::DISTRIBUTION, new FakeTermScope));
    }

    private function facet(): ChildTermsFacet
    {
        return new ChildTermsFacet('product_cat', 'Category');
    }
}
