<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\AttributesToSearchOn;

use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\SiteSearch\AttributesToSearchOn;

const ORDER = ['post_title', 'labels.category', 'excerpt', 'content'];

function searchOn(): AttributesToSearchOn
{
    return new AttributesToSearchOn(new class implements SearchableAttributes
    {
        public function all(): array
        {
            return ORDER;
        }
    });
}

it('keeps the order the index ranks fields in', function (): void {
    expect(searchOn()->among(['excerpt', 'post_title', 'labels.category']))->toBe(['post_title', 'labels.category', 'excerpt']);
});

it('drops a field the index does not search', function (): void {
    expect(searchOn()->among(['post_title', 'labels.product_brand', 'url']))->toBe(['post_title']);
});

it('names the fields the index does not search', function (): void {
    expect(searchOn()->outside(['post_title', 'url', 'excerpt']))->toBe(['url']);
});
