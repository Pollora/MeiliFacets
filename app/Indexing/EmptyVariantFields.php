<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\VariantFields;
use WC_Product_Variation;

final readonly class EmptyVariantFields implements VariantFields
{
    public function project(WC_Product_Variation $variation): array
    {
        return [];
    }
}
