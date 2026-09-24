<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum PlacedControl: string
{
    case Facet = 'facet';
    case Sort = 'sort';
}
