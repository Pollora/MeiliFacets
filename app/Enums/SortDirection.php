<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum SortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';

    private const string SEPARATOR = ':';

    public function of(string $field): string
    {
        return $field.self::SEPARATOR.$this->value;
    }

    public static function fieldOf(string $expression): string
    {
        return explode(self::SEPARATOR, $expression)[0];
    }
}
