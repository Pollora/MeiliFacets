<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ApplyShape: string
{
    case Block = 'block';
    case Pill = 'pill';

    /** The block keeps the markup the group always rendered: no shape is written for it. */
    public function mark(): ?string
    {
        return $this === self::Block ? null : $this->value;
    }

    /** The pill's count names what it applies; the block, alone under a column, names it in words. */
    public function label(): string
    {
        return match ($this) {
            self::Block => __('Apply filters'),
            self::Pill => __('Apply'),
        };
    }

    public function showsCount(): bool
    {
        return $this === self::Pill;
    }
}
