<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

use Modules\MeiliFacets\Contracts\ValuePresentation;

enum Presentation: string implements ValuePresentation
{
    case Control = 'control';
    case Pill = 'pill';

    public function slug(): string
    {
        return $this->value;
    }

    public function allowsSingleSelection(): bool
    {
        return $this === self::Control;
    }
}
