<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface ValuePresentation
{
    /** What `data-presentation` carries, for a stylesheet to hook on. */
    public function slug(): string;

    /** A radio cannot be unchecked, so a presentation that hides it cannot take one (R-10). */
    public function allowsSingleSelection(): bool;
}
