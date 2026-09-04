<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ImagePriority
{
    /** Above the fold: lazy loading would delay the LCP it is meant to help. */
    case Eager;

    case Lazy;

    public static function forRank(int $rank, int $eager): self
    {
        return $rank < $eager ? self::Eager : self::Lazy;
    }

    public function loading(): string
    {
        return $this === self::Eager ? 'eager' : 'lazy';
    }

    public function fetchPriority(): string
    {
        return $this === self::Eager ? 'high' : 'auto';
    }
}
