<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use WC_Product_Variation;

interface VariantFields
{
    /**
     * Card fields a project adds to the ones the module reads off the variation, and may override.
     * An empty string or array is left out.
     *
     * @return array<string, mixed>
     */
    public function project(WC_Product_Variation $variation): array;
}
