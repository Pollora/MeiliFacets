<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum BindingKind
{
    case Listed;
    case Standalone;
    case Template;
}
