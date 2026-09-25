<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ApplyShape: string
{
    use ComponentVariant;

    case Block = 'block';
    case Pill = 'pill';

    /** The block keeps the markup the group always rendered: no shape is written for it. */
    public function mark(): ?string
    {
        return $this === self::Block ? null : $this->value;
    }

    public function showsCount(): bool
    {
        return $this === self::Pill;
    }
}
