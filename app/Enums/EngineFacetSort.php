<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum EngineFacetSort: string
{
    case ByCount = 'count';
    case Alphabetical = 'alpha';
}
