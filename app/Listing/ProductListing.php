<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;
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

    private const string SEARCH_QUERY_VAR = 's';

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
        $pinned = $this->browsedTerm()?->taxonomy;

        return array_values(array_filter(
            $this->facets->all(),
            static fn (Placeable $filter): bool => $filter instanceof Facet && $filter->narrowsUnder($pinned)
        ));
    }

    public function filters(): array
    {
        return $this->facets->all();
    }

    public function sorts(): array
    {
        $sorts = $this->sorts->all();

        if (PriceFilter::isDeclaredAmong($this->filters())) {
            return $sorts;
        }

        return array_filter($sorts, $this->isOfferedWithoutPrice(...));
    }

    private function isOfferedWithoutPrice(Sort $sort): bool
    {
        return ! $sort->filtersOn(PriceField::OnSale->path());
    }

    public function baseFilter(): array
    {
        return [
            FilterExpression::equals('post_type', self::POST_TYPE),
            FilterExpression::equals('post_status', self::PUBLISHED),
            FilterExpression::without(
                DocumentField::Facets->path(ProductTaxonomy::Visibility->value),
                self::HIDDEN_FROM_CATALOG
            ),
            ...$this->browsedClause(),
        ];
    }

    /**
     * @return list<string>
     */
    private function browsedClause(): array
    {
        $browsed = $this->browsedTerm();

        return $browsed instanceof WP_Term
            ? [FilterExpression::equals(DocumentField::Facets->path($browsed->taxonomy), $browsed->slug)]
            : [];
    }

    public function baseQuery(): string
    {
        $term = is_search() ? (string) get_query_var(self::SEARCH_QUERY_VAR) : '';

        return mb_substr(trim($term), 0, StateReader::MAX_QUERY_LENGTH);
    }

    public function perPage(): int
    {
        return PageSize::forProducts();
    }

    public function applyMode(): ApplyMode
    {
        return ApplyMode::fromConfig();
    }

    /** Not `is_tax()`: it answers false on the built-in taxonomies, which a shop may well file products under. */
    private function browsedTerm(): ?WP_Term
    {
        $term = get_queried_object();

        // A product carries no field for a taxonomy that is not its own: filtering on it would empty the listing.
        if (! $term instanceof WP_Term || ! is_object_in_taxonomy(self::POST_TYPE, $term->taxonomy)) {
            return null;
        }

        return $term;
    }
}
