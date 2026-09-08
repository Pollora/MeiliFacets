<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Built by hand: the suite shares one application, so a binding made here would outlive the class. */
final class ProductFacetsTest extends TestCase
{
    #[Test]
    public function it_ships_category_and_brand_as_its_default(): void
    {
        $taxonomies = array_map(
            static fn (Facet $facet): string => $facet->taxonomy,
            new WooCommerceFacets(new NameOrder)->all()
        );

        $this->assertSame([ProductTaxonomy::Category->value, ProductTaxonomy::Brand->value], $taxonomies);
    }

    #[Test]
    public function it_ships_a_sort_by_price_and_by_date(): void
    {
        $this->assertSame(['price_asc', 'price_desc', 'newest'], array_keys(new WooCommerceSorts()->all()));
    }

    #[Test]
    public function it_shows_the_facets_it_was_given(): void
    {
        $listing = new ProductListing($this->facets('pa_couleur'), new WooCommerceSorts);

        $this->assertSame(['pa_couleur'], array_map(
            static fn (Facet $facet): string => $facet->taxonomy,
            $listing->facets()
        ));
    }

    #[Test]
    public function it_takes_its_facets_and_its_sorts_from_two_places(): void
    {
        $listing = new ProductListing(new WooCommerceFacets(new NameOrder), $this->sorts('rating'));

        $this->assertSame(['rating'], array_keys($listing->sorts()));
        $this->assertCount(2, $listing->facets());
    }

    #[Test]
    public function it_builds_its_facets_once(): void
    {
        $facets = new WooCommerceFacets(new NameOrder);

        $this->assertSame($facets->all(), $facets->all());
    }

    private function facets(string $taxonomy): ProductFacets
    {
        return new readonly class($taxonomy) implements ProductFacets
        {
            public function __construct(private string $taxonomy) {}

            public function all(): array
            {
                return [new Facet($this->taxonomy, 'Given')];
            }
        };
    }

    private function sorts(string $key): ProductSorts
    {
        return new readonly class($key) implements ProductSorts
        {
            public function __construct(private string $key) {}

            public function all(): array
            {
                return [$this->key => new Sort('Given', ['post_date:desc'])];
            }
        };
    }
}
