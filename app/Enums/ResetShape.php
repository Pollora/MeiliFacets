<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ResetShape: string
{
    use ComponentVariant;

    case Text = 'text';
    case Pill = 'pill';
    case Icon = 'icon';

    /** The text reset keeps the markup it always had: no shape is written for it. */
    public function mark(): ?string
    {
        return $this === self::Text ? null : $this->value;
    }
}
