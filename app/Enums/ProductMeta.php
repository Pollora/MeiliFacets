<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

// `_price` is stored as a number, so range filters need no typed projection.
enum ProductMeta: string
{
    case Price = '_price';
    case StockStatus = '_stock_status';

    public function path(): string
    {
        return DocumentField::Metas->path($this->value);
    }

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_map(
            static fn (self $meta): string => $meta->path(),
            self::cases()
        );
    }
}
