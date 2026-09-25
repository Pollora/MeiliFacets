<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** What a pill takes off: one term of a facet, or the whole price range. */
enum ActiveValueKind: string
{
    case Term = 'term';
    case Price = 'price';
}
