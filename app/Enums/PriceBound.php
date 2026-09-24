<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

use Modules\MeiliFacets\Listing\Range;

enum PriceBound: string
{
    case Min = 'min';
    case Max = 'max';

    public function of(Range $range): ?float
    {
        return match ($this) {
            self::Min => $range->min,
            self::Max => $range->max,
        };
    }

    public function parameter(): QueryParameter
    {
        return match ($this) {
            self::Min => QueryParameter::MinPrice,
            self::Max => QueryParameter::MaxPrice,
        };
    }

    public function hook(): Hook
    {
        return match ($this) {
            self::Min => Hook::PriceMin,
            self::Max => Hook::PriceMax,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Min => __('From'),
            self::Max => __('To'),
        };
    }

    public function handleLabel(): string
    {
        return match ($this) {
            self::Min => __('Lowest price'),
            self::Max => __('Highest price'),
        };
    }
}
