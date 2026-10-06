<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\VariantScopedListing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Search\VisibleProducts;

/** Products filtered by brand, size and price, whose sizes have a document each. */
final readonly class FakeVariantScopedListing implements VariantScopedListing
{
    public const string SIZE = 'pa_size';

    private FakeListing $listing;

    public function __construct()
    {
        $this->listing = new FakeListing(
            [new Facet('product_brand', 'Brand'), new Facet(self::SIZE, 'Size'), new PriceFilter('Price')],
            VisibleProducts::inCatalogue(),
            sorts: [
                'price_asc' => new Sort('Price', ['price.min:asc']),
                'newest' => new Sort('Newest', ['post_date:desc']),
            ],
        );
    }

    public function variantTaxonomies(): array
    {
        return [self::SIZE];
    }

    public function variantFilter(): array
    {
        return VisibleProducts::onVariants($this->baseFilter());
    }

    public function variantSearchScope(): SearchScope
    {
        return new SearchScope(VisibleProducts::onVariants(VisibleProducts::inSearch()));
    }

    public function searchScope(): SearchScope
    {
        return new SearchScope(VisibleProducts::inSearch());
    }

    public function name(): string
    {
        return $this->listing->name();
    }

    public function facets(): array
    {
        return $this->listing->facets();
    }

    public function filters(): array
    {
        return $this->listing->filters();
    }

    public function sorts(): array
    {
        return $this->listing->sorts();
    }

    public function baseFilter(): array
    {
        return $this->listing->baseFilter();
    }

    public function baseQuery(): string
    {
        return $this->listing->baseQuery();
    }

    public function perPage(): int
    {
        return $this->listing->perPage();
    }

    public function applyMode(): ApplyMode
    {
        return $this->listing->applyMode();
    }
}
