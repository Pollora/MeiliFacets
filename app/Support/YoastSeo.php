<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class YoastSeo
{
    public static function isActive(): bool
    {
        return function_exists('YoastSEO');
    }
}
