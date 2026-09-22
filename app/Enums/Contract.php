<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

use Illuminate\Support\HtmlString;

enum Contract: string
{
    case Attribute = 'data-meili';
    case VersionAttribute = 'data-meili-contract';
    case ScrollAttribute = 'data-meili-scroll';

    /** Incremented when a hook is renamed or removed, never when one is added. */
    public const int VERSION = 1;

    public static function version(): HtmlString
    {
        return new HtmlString(self::VersionAttribute->value.'="'.self::VERSION.'"');
    }
}
