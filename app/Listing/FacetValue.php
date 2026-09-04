<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class FacetValue
{
    public function __construct(
        public string $slug,
        public string $label,
        public int $count,
        public bool $selected,
        public bool $folded,
    ) {}
}
