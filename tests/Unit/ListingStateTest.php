<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Sort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListingStateTest extends TestCase
{
    #[Test]
    public function it_hands_back_the_sort_the_state_names(): void
    {
        $newest = new Sort('New arrivals', ['post_date:desc']);

        $this->assertSame($newest, new ListingState(sort: 'newest')->chosenSort(['newest' => $newest]));
    }

    #[Test]
    public function it_chooses_no_sort_while_none_is_named(): void
    {
        $this->assertNull(new ListingState()->chosenSort(['' => new Sort('Unnamed', ['post_date:desc'])]));
    }

    #[Test]
    public function it_chooses_no_sort_the_listing_does_not_offer(): void
    {
        $this->assertNull(new ListingState(sort: 'rating')->chosenSort(['newest' => new Sort('New arrivals', ['post_date:desc'])]));
    }

    #[Test]
    public function it_selects_any_of_the_taxonomies_one_of_its_facets_names(): void
    {
        $this->assertTrue(new ListingState(['pa_volume' => ['400ml']])->selectsAnyOf(['pa_scent', 'pa_volume']));
    }

    #[Test]
    public function it_selects_none_of_the_taxonomies_no_facet_names(): void
    {
        $this->assertFalse(new ListingState(['product_brand' => ['acme']])->selectsAnyOf(['pa_volume']));
        $this->assertFalse(new ListingState()->selectsAnyOf(['pa_volume']));
    }
}
