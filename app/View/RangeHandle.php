<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Support\Money;

/** One end of a range. */
final class RangeHandle
{
    private ?string $written = null;

    public function __construct(
        public readonly PriceBound $bound,
        public readonly string $parameter,
        public readonly float $value,
        /** An open end has no number to show, while the handle drawn for it still needs one. */
        public readonly ?float $shown,
        public readonly float $floor,
        public readonly float $ceiling,
        public readonly float $at,
        private readonly Money $money,
    ) {}

    public function written(): string
    {
        return $this->written ??= $this->money->of($this->value);
    }
}
