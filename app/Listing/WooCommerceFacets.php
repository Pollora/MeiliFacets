<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Enums\DisplayOrder;
use Modules\MeiliFacets\Enums\ProductTaxonomy;

final class WooCommerceFacets implements ProductFacets
{
    /** @var list<Facet>|null */
    private ?array $facets = null;

    public function all(): array
    {
        return $this->facets ??= [
            new ChildTermsFacet(ProductTaxonomy::Category->value, __('Category'), order: DisplayOrder::Name),
            new Facet(ProductTaxonomy::Brand->value, __('Brand'), order: DisplayOrder::Name),
        ];
    }
}
