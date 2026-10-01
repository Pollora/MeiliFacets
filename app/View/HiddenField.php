<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

final readonly class HiddenField
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
