<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

use Illuminate\Support\HtmlString;

final readonly class Contract
{
    public const string ATTRIBUTE = 'data-meili';

    public const string VERSION_ATTRIBUTE = 'data-meili-contract';

    /** Incremented whenever a hook is added, renamed or removed. */
    public const int VERSION = 1;

    public static function version(): HtmlString
    {
        return new HtmlString(self::VERSION_ATTRIBUTE.'="'.self::VERSION.'"');
    }
}
