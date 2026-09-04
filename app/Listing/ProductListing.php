<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\ProductMeta;
use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Search\FilterExpression;

final readonly class ProductListing implements Listing
{
    public const string NAME = 'products';

    private const string POST_TYPE = 'product';

    private const string PUBLISHED = 'publish';

    private const string BRAND = 'product_brand';

    private const string SIZE = 'pa_contenance';

    private const string CATEGORY = 'product_cat';

    private const string HIDDEN_FROM_CATALOG = 'exclude-from-catalog';

    private const string VISIBILITY = 'product_visibility';

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @return list<Facet>
     */
    public function facets(): array
    {
        $facets = [
            new Facet(self::BRAND, __('Brand')),
            new Facet(self::SIZE, __('Volume')),
        ];

        // On a category archive the aisle is already carried by the path.
        if (! is_tax(self::CATEGORY)) {
            $facets[] = new Facet(self::CATEGORY, __('Category'), SelectionMode::Single);
        }

        return $facets;
    }

    /**
     * @return array<string, Sort>
     */
    public function sorts(): array
    {
        return [
            'price_asc' => new Sort(__('Price, low to high'), [ProductMeta::Price->path().':asc']),
            'price_desc' => new Sort(__('Price, high to low'), [ProductMeta::Price->path().':desc']),
            'newest' => new Sort(__('New arrivals'), ['post_date:desc']),
        ];
    }

    /**
     * @return list<string>
     */
    public function baseFilter(): array
    {
        $clauses = [
            FilterExpression::equals('post_type', self::POST_TYPE),
            FilterExpression::equals('post_status', self::PUBLISHED),
            FilterExpression::without(DocumentField::Facets->path(self::VISIBILITY), self::HIDDEN_FROM_CATALOG),
        ];

        $aisle = $this->currentAisle();

        return $aisle === null
            ? $clauses
            : [...$clauses, FilterExpression::equals(DocumentField::Facets->path(self::CATEGORY), $aisle)];
    }

    public function perPage(): int
    {
        return PageSize::forProducts();
    }

    public function applyMode(): ApplyMode
    {
        return ApplyMode::fromConfig();
    }

    private function currentAisle(): ?string
    {
        $term = is_tax(self::CATEGORY) ? get_queried_object() : null;

        return $term instanceof \WP_Term ? $term->slug : null;
    }
}
