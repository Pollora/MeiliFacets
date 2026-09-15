<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class Range
{
    public function __construct(
        public ?float $min = null,
        public ?float $max = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->min === null && $this->max === null;
    }

    public function clamp(float $value): float
    {
        return min(max($value, $this->min ?? $value), $this->max ?? $value);
    }

    /** Without a span, and without a value, everything sits at the start. */
    public function ratio(?float $value): float
    {
        $span = ($this->max ?? 0.0) - ($this->min ?? 0.0);

        return $value === null || $span <= 0.0
            ? 0.0
            : round(($this->clamp($value) - ($this->min ?? 0.0)) / $span, 4);
    }
}
