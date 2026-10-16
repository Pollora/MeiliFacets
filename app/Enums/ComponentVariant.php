<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** A variant a component takes as an attribute: written plainly it arrives as a string, bound (`:shape`) as a case. */
trait ComponentVariant
{
    public static function fromAttribute(self|string $given): self
    {
        return is_string($given) ? self::from($given) : $given;
    }
}
