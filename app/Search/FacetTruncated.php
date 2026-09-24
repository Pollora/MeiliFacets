<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use RuntimeException;

/**
 * The engine truncates a distribution by global count, before a facet narrows it to the
 * aisle being looked at: a value it drops may be the only one that mattered on this page.
 */
final class FacetTruncated extends RuntimeException
{
    public static function at(string $taxonomy, int $values): self
    {
        return new self(
            "Facet \"{$taxonomy}\" returned {$values} values, the ceiling the engine applies. "
            .'Values beyond it were dropped before the facet could narrow them. '
            .'Raise meilifacets.engine.max_facet_values, then reindex to push the setting. '
            .'A taxonomy that happens to use exactly that many values reports this too.'
        );
    }
}
