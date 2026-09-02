<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum FacetValueOrder: string
{
    case ByCount = 'count';
    case Alphabetical = 'alpha';
}
