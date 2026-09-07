<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum InputType: string
{
    case Checkbox = 'checkbox';
    case Radio = 'radio';

    public static function forSelection(SelectionMode $selection): self
    {
        return $selection->allowsSeveralValues() ? self::Checkbox : self::Radio;
    }
}
