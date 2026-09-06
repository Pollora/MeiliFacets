<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/**
 * What a facet does with the term a taxonomy falls back to — « Uncategorized »,
 * « Non classé ». It says a content was filed nowhere, which is a fact about the
 * catalogue rather than a way to browse it.
 */
enum DefaultTerm
{
    case Hidden;

    /** For a taxonomy whose fallback term is a real one an editor chose. */
    case Shown;
}
