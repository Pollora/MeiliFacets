<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ActiveValueKind: string
{
    case Search = 'search';
    case Term = 'term';
    case Price = 'price';
}
