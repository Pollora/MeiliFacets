<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/**
 * How a facet shows the values the engine returned. The engine counts them
 * first in every case — that is what decides which ones survive the cap.
 */
enum DisplayOrder
{
    /** Most results first: the only readable order for a long tail of values. */
    case Count;

    /** Numeric-aware, so "10ml" precedes "500ml" instead of following it. */
    case Name;
}
