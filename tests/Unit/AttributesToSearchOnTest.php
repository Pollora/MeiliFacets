<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\SiteSearch\AttributesToSearchOn;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributesToSearchOnTest extends TestCase
{
    public const array ORDER = ['post_title', 'labels.category', 'excerpt', 'content'];

    #[Test]
    public function it_keeps_the_order_the_index_ranks_fields_in(): void
    {
        $this->assertSame(
            ['post_title', 'labels.category', 'excerpt'],
            $this->searchOn()->among(['excerpt', 'post_title', 'labels.category'])
        );
    }

    #[Test]
    public function it_drops_a_field_the_index_does_not_search(): void
    {
        $this->assertSame(['post_title'], $this->searchOn()->among(['post_title', 'labels.product_brand', 'url']));
    }

    #[Test]
    public function it_names_the_fields_the_index_does_not_search(): void
    {
        $this->assertSame(['url'], $this->searchOn()->outside(['post_title', 'url', 'excerpt']));
    }

    private function searchOn(): AttributesToSearchOn
    {
        return new AttributesToSearchOn(new class implements SearchableAttributes
        {
            public function all(): array
            {
                return AttributesToSearchOnTest::ORDER;
            }
        });
    }
}
