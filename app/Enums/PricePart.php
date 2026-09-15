<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** The two inputs exist whatever is declared. */
enum PricePart: string
{
    case Fields = 'fields';
    case Slider = 'slider';
}
