<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class UniqueList
{
    /**
     * First occurrence wins, so the order of the lists is kept.
     *
     * @param  list<string>  ...$lists
     * @return list<string>
     */
    public static function merge(array ...$lists): array
    {
        return array_values(array_unique(array_merge(...$lists)));
    }
}
