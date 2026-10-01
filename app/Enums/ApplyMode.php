<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ApplyMode: string
{
    /** One search per submit: fewer queries, one reload per change set. */
    case OnSubmit = 'submit';

    /** One search per ticked box: no submit button, more queries. */
    case Immediate = 'immediate';

    public const self DEFAULT = self::OnSubmit;

    public function needsButton(): bool
    {
        return $this === self::OnSubmit;
    }
}
