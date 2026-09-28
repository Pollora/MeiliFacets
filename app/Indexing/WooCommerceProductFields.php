<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Enums\SearchedMeta;

/** What names a product: its brand, its category, its SKU. */
final readonly class WooCommerceProductFields
{
    /**
     * @param  Closure(): bool  $pluginIsActive  asked on every read, not when the index is wired (R-171)
     */
    public function __construct(private Closure $pluginIsActive) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        if (! ($this->pluginIsActive)()) {
            return [];
        }

        return [
            DocumentField::Labels->path(ProductTaxonomy::Brand->value),
            DocumentField::Labels->path(ProductTaxonomy::Category->value),
            SearchedMeta::Sku->path(),
        ];
    }
}
