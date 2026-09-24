<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\ValuePresentation;

/** What a theme declares when the module's presentations are not enough. */
enum FakePresentation: string implements ValuePresentation
{
    case Swatch = 'swatch';
    case Tile = 'tile';

    public function slug(): string
    {
        return $this->value;
    }

    public function allowsSingleSelection(): bool
    {
        return $this === self::Swatch;
    }
}
