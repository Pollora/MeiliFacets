<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum VariantField: string
{
    case Facets = 'facets';
    case Price = 'price';
    case Fields = 'fields';
    case InStock = 'in_stock';
}
