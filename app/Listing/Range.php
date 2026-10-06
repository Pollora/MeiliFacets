<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class Range
{
    public function __construct(
        public ?float $min = null,
        public ?float $max = null,
    ) {}

    /** `(string) 1.0E-9` is neither a filter nor a URL value. */
    public static function formatBound(float $bound): string
    {
        return rtrim(rtrim(sprintf('%.4F', $bound), '0'), '.');
    }

    public function isEmpty(): bool
    {
        return $this->min === null && $this->max === null;
    }

    /** An inverted range — from 50 up to 10 — holds nothing, as the engine's two bounds match nothing. */
    public function contains(float $value): bool
    {
        return ($this->min ?? $value) <= $value && $value <= ($this->max ?? $value);
    }

    public function clamp(float $value): float
    {
        $floored = max($value, $this->min ?? $value);

        return min($floored, $this->max ?? $floored);
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
