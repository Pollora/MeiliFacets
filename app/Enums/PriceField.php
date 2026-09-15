<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum PriceField: string
{
    case Min = 'min';
    case Max = 'max';
    case OnSale = 'onsale';

    public function path(): string
    {
        return DocumentField::Price->path($this->value);
    }

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_map(
            static fn (self $field): string => $field->path(),
            self::cases()
        );
    }
}
