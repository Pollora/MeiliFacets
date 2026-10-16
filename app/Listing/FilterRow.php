<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class FilterRow
{
    public function __construct(
        public int $mark,
        public int $limit,
    ) {}
}
