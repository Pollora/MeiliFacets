<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

// `page`, `paged` and `order` are public WordPress query vars: none can be used here.
enum QueryParameter: string
{
    case Sort = 'sort';
    case Query = 'q';
    case Page = 'pg';
    case MinPrice = 'min_price';
    case MaxPrice = 'max_price';

    /** The key the browser reads it under, which is not the name it takes in a URL. */
    public function key(): string
    {
        return match ($this) {
            self::Sort => 'sort',
            self::Query => 'query',
            self::Page => 'page',
            self::MinPrice => 'minPrice',
            self::MaxPrice => 'maxPrice',
        };
    }
}
