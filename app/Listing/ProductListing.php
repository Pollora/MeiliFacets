<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Search\FilterExpression;
use Modules\MeiliFacets\Support\WooCommerce;
use WP_Term;

final readonly class ProductListing implements Listing
{
    public const string NAME = 'products';

    private const string POST_TYPE = 'product';

    private const string PUBLISHED = 'publish';

    private const string HIDDEN_FROM_CATALOG = 'exclude-from-catalog';

    /** Discovery builds every listing it finds: the dependency has to refuse itself. */
    public function __construct(
        private ProductFacets $facets,
        private ProductSorts $sorts,
    ) {
        if (! WooCommerce::isActive()) {
            throw new ListingUnavailable('WooCommerce is not active: there are no products to list.');
        }
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function facets(): array
    {
        return $this->facets->all();
    }

    public function sorts(): array
    {
        return $this->sorts->all();
    }

    public function baseFilter(): array
    {
        $clauses = [
            FilterExpression::equals('post_type', self::POST_TYPE),
            FilterExpression::equals('post_status', self::PUBLISHED),
            FilterExpression::without(
                DocumentField::Facets->path(ProductTaxonomy::Visibility->value),
                self::HIDDEN_FROM_CATALOG
            ),
        ];

        $aisle = $this->currentAisle();

        return $aisle === null
            ? $clauses
            : [...$clauses, FilterExpression::equals(
                DocumentField::Facets->path(ProductTaxonomy::Category->value),
                $aisle
            )];
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
        $term = is_tax(ProductTaxonomy::Category->value) ? get_queried_object() : null;

        return $term instanceof WP_Term ? $term->slug : null;
    }
}
