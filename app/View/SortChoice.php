<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

final readonly class SortChoice
{
    public function __construct(
        public string $value,
        public string $label,
        public string $id,
        public bool $selected,
    ) {}
}
