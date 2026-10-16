<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum RowOverflow: string
{
    use ComponentVariant;

    case SideSheet = 'side-sheet';
    case ShowMore = 'show-more';

    public const self DEFAULT = self::SideSheet;
}
