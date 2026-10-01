<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Support\NumberText;

final readonly class CardFieldValue
{
    public function __construct(private mixed $value) {}

    public function text(): string
    {
        if (is_string($this->value)) {
            return $this->value;
        }

        return $this->isFiniteNumber() ? NumberText::from((float) $this->value) : '';
    }

    public function isTrue(): bool
    {
        return match (true) {
            is_bool($this->value) => $this->value,
            is_int($this->value) => $this->value !== 0,
            is_float($this->value) => $this->value !== 0.0,
            is_string($this->value) => $this->value !== '',
            is_array($this->value) => $this->value !== [],
            default => false,
        };
    }

    private function isFiniteNumber(): bool
    {
        return is_int($this->value) || (is_float($this->value) && is_finite($this->value));
    }
}
