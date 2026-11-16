<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Listing\VariationTaxonomies;
use Modules\MeiliFacets\Listing\WooCommerceDefaultSort;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Support\UrlParameters;
use Tests\TestCase;

trait ProductFacetsSteps
{
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

    private function facetsWithPrice(): ProductFacets
    {
        return new readonly class implements ProductFacets
        {
            public function all(): array
            {
                return [new PriceFilter('Price')];
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

// Built by hand: the suite shares one application, so a binding made here would outlive the class.
uses(TestCase::class, ProductFacetsSteps::class);

it('ships category and brand as its default', function (): void {
    $taxonomies = array_map(
        static fn (Facet $facet): string => $facet->taxonomy,
        new WooCommerceFacets(new NameOrder)->all()
    );

    expect($taxonomies)->toBe([ProductTaxonomy::Category->value, ProductTaxonomy::Brand->value]);
});

it('ships sorts by price by date and one for promotions', function (): void {
    expect(array_keys(new WooCommerceSorts()->all()))->toBe(['price_asc', 'price_desc', 'newest', 'on_sale']);
});

it('shows the facets it was given', function (): void {
    $listing = new ProductListing($this->facets('pa_couleur'), new WooCommerceSorts, $this->app->make(SearchableTypes::class), new VariationTaxonomies, new WooCommerceDefaultSort);

    expect(array_map(
        static fn (Facet $facet): string => $facet->taxonomy,
        $listing->facets()
    ))->toBe(['pa_couleur']);
});

it('takes its facets and its sorts from two places', function (): void {
    $listing = new ProductListing(new WooCommerceFacets(new NameOrder), $this->sorts('rating'), $this->app->make(SearchableTypes::class), new VariationTaxonomies, new WooCommerceDefaultSort);

    expect(array_keys($listing->sorts()))->toBe(['rating']);
    expect($listing->facets())->toHaveCount(2);
});

it('builds its facets once', function (): void {
    $facets = new WooCommerceFacets(new NameOrder);

    expect($facets->all())->toBe($facets->all());
});

it('offers promotions only on a listing that declares a price', function (): void {
    $withoutPrice = new ProductListing(new WooCommerceFacets(new NameOrder), new WooCommerceSorts, $this->app->make(SearchableTypes::class), new VariationTaxonomies, new WooCommerceDefaultSort);
    $withPrice = new ProductListing($this->facetsWithPrice(), new WooCommerceSorts, $this->app->make(SearchableTypes::class), new VariationTaxonomies, new WooCommerceDefaultSort);

    expect($withoutPrice->sorts())->not->toHaveKey('on_sale');
    expect($withPrice->sorts())->toHaveKey('on_sale');
    expect(new StateReader(new UrlParameters([]))->read($withoutPrice, ['sort' => 'on_sale'])->sort)->toBeNull();
});
