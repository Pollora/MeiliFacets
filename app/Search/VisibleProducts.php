<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\DocumentKind;
use Modules\MeiliFacets\Enums\ProductTaxonomy;

/** WooCommerce swaps the flag on a search rather than adding one (`WC_Query::get_tax_query()`). */
final readonly class VisibleProducts
{
    public const string POST_TYPE = 'product';

    public const string VARIATION_POST_TYPE = 'product_variation';

    private const string HIDDEN_FROM_CATALOG = 'exclude-from-catalog';

    private const string HIDDEN_FROM_SEARCH = 'exclude-from-search';

    /**
     * @param  list<string>  $clauses
     * @return list<string>
     */
    public static function onVariants(array $clauses): array
    {
        return [...array_map(self::withVariants(...), $clauses), self::withoutParents()];
    }

    /**
     * @return list<string>
     */
    public static function inCatalogue(): array
    {
        return self::hiding(self::HIDDEN_FROM_CATALOG);
    }

    /**
     * @return list<string>
     */
    public static function inSearch(): array
    {
        return self::hiding(self::HIDDEN_FROM_SEARCH);
    }

    /**
     * @return list<string>
     */
    private static function hiding(string $flag): array
    {
        return [
            ...PublishedPosts::of(self::POST_TYPE),
            FilterExpression::without(DocumentField::Facets->path(ProductTaxonomy::Visibility->value), $flag),
        ];
    }

    private static function withVariants(string $clause): string
    {
        return $clause === self::ofProducts() ? self::ofProductsOrVariations() : $clause;
    }

    private static function ofProducts(): string
    {
        return FilterExpression::equals(DocumentField::PostType->value, self::POST_TYPE);
    }

    private static function ofProductsOrVariations(): string
    {
        return FilterExpression::any([
            self::ofProducts(),
            FilterExpression::equals(DocumentField::PostType->value, self::VARIATION_POST_TYPE),
        ]);
    }

    private static function withoutParents(): string
    {
        return FilterExpression::without(DocumentField::Kind->value, DocumentKind::Parent->value);
    }
}
