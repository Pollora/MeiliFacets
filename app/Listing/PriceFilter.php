<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use BackedEnum;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\PricePart;

final readonly class PriceFilter implements Placeable
{
    public string $name;

    /**
     * @param  list<PricePart>  $parts
     */
    public function __construct(
        public string $label,
        public array $parts = [PricePart::Fields],
        string|BackedEnum $name = 'price',
    ) {
        $this->name = $name instanceof BackedEnum ? (string) $name->value : $name;
    }

    public function shows(PricePart $part): bool
    {
        return in_array($part, $this->parts, true);
    }

    /** A legend cannot sit on a flex row: with a slider, the control names itself. */
    public function namesItself(): bool
    {
        return $this->shows(PricePart::Slider);
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return [PriceField::Min->path(), PriceField::Max->path()];
    }

    /**
     * @param  array<string, array<string, float>>  $stats
     */
    public function boundsFrom(array $stats): Range
    {
        $min = $stats[PriceField::Min->path()]['min'] ?? null;
        $max = $stats[PriceField::Max->path()]['max'] ?? null;

        // One end alone draws nothing, and leaves the other reading `aria-valuemax=""`.
        return $min === null || $max === null ? new Range : new Range(floor($min), ceil($max));
    }
}
