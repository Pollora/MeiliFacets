<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Enums\SearchedMeta;

/** Title, then what names a product, then every other label, then the prose. */
final readonly class DefaultSearchableAttributes implements SearchableAttributes
{
    /**
     * Both are asked on every read, not when the index is wired (R-171).
     *
     * @param  Closure(): list<string>  $indexedTaxonomies
     * @param  Closure(): bool  $wooCommerceIsActive
     */
    public function __construct(
        private Closure $indexedTaxonomies,
        private Closure $wooCommerceIsActive,
    ) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $ranked = [
            DocumentField::Title->value,
            ...$this->productFields(),
            ...$this->labelFields(),
            DocumentField::Excerpt->value,
            DocumentField::Content->value,
        ];

        return array_values(array_unique($ranked));
    }

    /**
     * @return list<string>
     */
    private function productFields(): array
    {
        if (! ($this->wooCommerceIsActive)()) {
            return [];
        }

        return [
            DocumentField::Labels->path(ProductTaxonomy::Brand->value),
            DocumentField::Labels->path(ProductTaxonomy::Category->value),
            SearchedMeta::Sku->path(),
        ];
    }

    /**
     * `product_visibility` files `exclude-from-search` and `featured`: labels no visitor searches by.
     *
     * @return list<string>
     */
    private function labelFields(): array
    {
        $viewable = array_filter(($this->indexedTaxonomies)(), is_taxonomy_viewable(...));

        return array_values(array_map(DocumentField::Labels->path(...), $viewable));
    }
}
