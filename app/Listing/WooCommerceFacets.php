<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Enums\ProductTaxonomy;

final class WooCommerceFacets implements ProductFacets
{
    /** @var list<Facet>|null */
    private ?array $facets = null;

    public function __construct(private readonly NameOrder $names) {}

    public function all(): array
    {
        return $this->facets ??= [
            new ChildTermsFacet(ProductTaxonomy::Category->value, __('Category'), order: $this->names),
            new Facet(ProductTaxonomy::Brand->value, __('Brand'), order: $this->names),
        ];
    }
}
