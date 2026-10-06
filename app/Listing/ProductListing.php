<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Illuminate\Container\Attributes\Config;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Contracts\VariantScopedListing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Search\FilterExpression;
use Modules\MeiliFacets\Search\VisibleProducts;
use Modules\MeiliFacets\SiteSearch\SearchableType;
use Modules\MeiliFacets\Support\WooCommerce;
use WP_Term;

final readonly class ProductListing implements VariantScopedListing
{
    public const string NAME = 'products';

    private const string SEARCH_QUERY_VAR = 's';

    private ApplyMode $applyMode;

    /** Discovery builds every listing it finds: the dependency has to refuse itself. */
    public function __construct(
        private ProductFacets $facets,
        private ProductSorts $sorts,
        private SearchableTypes $searchableTypes,
        private VariationTaxonomies $variationTaxonomies,
        #[Config('meilifacets.apply_mode', ApplyMode::DEFAULT->value)] string $applyMode = ApplyMode::DEFAULT->value,
    ) {
        $this->applyMode = ApplyMode::tryFrom($applyMode) ?? ApplyMode::DEFAULT;

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
            ...VisibleProducts::inCatalogue(),
            ...$this->browsedClause(),
        ];
    }

    public function searchScope(): SearchScope
    {
        $product = $this->searchableTypes->all()[VisibleProducts::POST_TYPE] ?? null;

        if ($product instanceof SearchableType) {
            return $product->scope($this->browsedClause());
        }

        return new SearchScope([...VisibleProducts::inSearch(), ...$this->browsedClause()]);
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

    public function variantTaxonomies(): array
    {
        return $this->variationTaxonomies->all();
    }

    public function variantFilter(): array
    {
        return VisibleProducts::onVariants($this->baseFilter());
    }

    public function variantSearchScope(): SearchScope
    {
        $scope = $this->searchScope();

        return new SearchScope(VisibleProducts::onVariants($scope->filter), $scope->fields);
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
        return $this->applyMode;
    }

    /** Not `is_tax()`: it answers false on the built-in taxonomies, which a shop may well file products under. */
    private function browsedTerm(): ?WP_Term
    {
        $term = get_queried_object();

        // A product carries no field for a taxonomy that is not its own: filtering on it would empty the listing.
        if (! $term instanceof WP_Term || ! is_object_in_taxonomy(VisibleProducts::POST_TYPE, $term->taxonomy)) {
            return null;
        }

        return $term;
    }
}
