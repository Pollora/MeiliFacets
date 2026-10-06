<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum DocumentKind: string
{
    /** A product whose variants have documents of their own. */
    case Parent = 'parent';
}
