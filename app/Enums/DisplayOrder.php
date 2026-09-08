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

    /**
     * The order the taxonomy itself lists its terms in. WooCommerce lets a shop
     * set that per attribute — drag and drop, name, numeric name or term id —
     * and applies it to every `get_terms()`; this reads it rather than guessing.
     */
    case Declared;
}
