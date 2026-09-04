<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

// `page`, `paged` and `order` are public WordPress query vars: none can be used here.
enum QueryParameter: string
{
    case Sort = 'sort';
    case Query = 'q';
    case Page = 'pg';
}
