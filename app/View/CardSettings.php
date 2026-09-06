<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

final readonly class CardSettings
{
    /** How many cards a theme fits above the fold: layout-dependent, so overridable. */
    public const int DEFAULT_EAGER = 4;

    public function __construct(public int $eager = self::DEFAULT_EAGER) {}
}
