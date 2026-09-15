<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Sort;

final readonly class FakeListing implements Listing
{
    /**
     * @param  list<Placeable>  $facets
     * @param  list<string>  $baseFilter
     */
    public function __construct(
        private array $facets = [],
        private array $baseFilter = [],
        private int $perPage = 16,
        private string $name = 'fake',
    ) {}

    public static function named(string $name): self
    {
        return new self(name: $name);
    }

    public static function withBrandAndCategory(): self
    {
        return new self([
            new Facet('product_brand', 'Brand'),
            new Facet('product_cat', 'Category', SelectionMode::Single),
        ], ['post_type = "product"']);
    }

    public static function withPriceAndBrand(): self
    {
        return new self([
            new Facet('product_brand', 'Brand'),
            new PriceFilter('Price'),
        ], ['post_type = "product"']);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<Facet>
     */
    public function facets(): array
    {
        return array_values(array_filter(
            $this->facets,
            static fn (Placeable $filter): bool => $filter instanceof Facet
        ));
    }

    public function filters(): array
    {
        return $this->facets;
    }

    /**
     * @return array<string, Sort>
     */
    public function sorts(): array
    {
        return ['price_asc' => new Sort('Price', ['metas._price:asc'])];
    }

    /**
     * @return list<string>
     */
    public function baseFilter(): array
    {
        return $this->baseFilter;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function applyMode(): ApplyMode
    {
        return ApplyMode::OnSubmit;
    }
}
