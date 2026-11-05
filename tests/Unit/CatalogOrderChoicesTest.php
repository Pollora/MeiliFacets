<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\CatalogOrderChoices;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogOrderChoicesTest extends TestCase
{
    #[Test]
    public function it_offers_only_the_sortings_the_listing_follows_keeping_their_labels_and_order(): void
    {
        $choices = [
            'menu_order' => 'Default sorting',
            'popularity' => 'Popularity',
            'rating' => 'Average rating',
            'date' => 'Sort by latest',
            'price' => 'Sort by price: low to high',
            'price-desc' => 'Sort by price: high to low',
        ];

        $this->assertSame([
            'menu_order' => 'Default sorting',
            'date' => 'Sort by latest',
            'price' => 'Sort by price: low to high',
            'price-desc' => 'Sort by price: high to low',
        ], new CatalogOrderChoices()->onlyFollowable($choices));
    }

    #[Test]
    public function it_drops_a_choice_another_plugin_added_under_an_unknown_or_numeric_key(): void
    {
        $choices = ['title' => 'Name', 'random' => 'Random', 0 => 'Numbered'];

        $this->assertSame(['title' => 'Name'], new CatalogOrderChoices()->onlyFollowable($choices));
    }

    #[Test]
    public function it_offers_nothing_when_given_nothing(): void
    {
        $this->assertSame([], new CatalogOrderChoices()->onlyFollowable([]));
    }
}
