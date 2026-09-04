<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum SelectionMode
{
    case Multiple;
    case Single;

    public function allowsSeveralValues(): bool
    {
        return $this === self::Multiple;
    }
}
