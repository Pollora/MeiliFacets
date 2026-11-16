<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\CatalogOrderChoices;

use Modules\MeiliFacets\Listing\CatalogOrderChoices;

it('offers only the sortings the listing follows keeping their labels and order', function (): void {
    $choices = [
        'menu_order' => 'Default sorting',
        'popularity' => 'Popularity',
        'rating' => 'Average rating',
        'date' => 'Sort by latest',
        'price' => 'Sort by price: low to high',
        'price-desc' => 'Sort by price: high to low',
    ];

    expect(new CatalogOrderChoices()->onlyFollowable($choices))->toBe([
        'menu_order' => 'Default sorting',
        'date' => 'Sort by latest',
        'price' => 'Sort by price: low to high',
        'price-desc' => 'Sort by price: high to low',
    ]);
});

it('drops a choice another plugin added under an unknown or numeric key', function (): void {
    $choices = ['title' => 'Name', 'random' => 'Random', 0 => 'Numbered'];

    expect(new CatalogOrderChoices()->onlyFollowable($choices))->toBe(['title' => 'Name']);
});

it('offers nothing when given nothing', function (): void {
    expect(new CatalogOrderChoices()->onlyFollowable([]))->toBe([]);
});
