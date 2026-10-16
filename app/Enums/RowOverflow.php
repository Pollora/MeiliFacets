<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum RowOverflow: string
{
    use ComponentVariant;

    case SideSheet = 'side_sheet';
    case ShowMore = 'show_more';

    public const self DEFAULT = self::SideSheet;

    public function slug(): string
    {
        return match ($this) {
            self::SideSheet => 'side-sheet',
            self::ShowMore => 'show-more',
        };
    }
}
