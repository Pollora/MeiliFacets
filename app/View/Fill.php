<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/** Two ratios from 0 to 1, not two prices: what part of the track reads as selected. */
final readonly class Fill
{
    public function __construct(
        public float $from,
        public float $to,
    ) {}
}
