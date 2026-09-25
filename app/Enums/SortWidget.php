<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum SortWidget: string
{
    use ComponentVariant;

    case Listbox = 'listbox';
    case Radios = 'radios';
}
