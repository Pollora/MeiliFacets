<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** Whether a trigger's label is read out, or only shown because its slot says it again. */
enum LabelReading
{
    case Aloud;
    case Silent;
}
