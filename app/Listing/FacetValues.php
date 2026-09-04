<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\TermLabels;

/**
 * Turns a raw distribution into displayable values: engine order kept, labels
 * read from the taxonomy, everything past the visible limit folded away.
 */
final readonly class FacetValues
{
    public function __construct(private TermLabels $labels) {}

    /**
     * @param  array<string, int>  $distribution  slug to count
     * @return list<FacetValue>
     */
    public function of(Facet $facet, array $distribution, ListingState $state): array
    {
        $slugs = array_slice(array_keys($distribution), 0, $facet->cap);
        $labels = $this->labels->of($facet->taxonomy, $slugs);
        $values = [];

        foreach (array_values($slugs) as $rank => $slug) {
            $values[] = new FacetValue(
                $slug,
                $labels[$slug] ?? $slug,
                $distribution[$slug],
                $state->holds($facet->taxonomy, $slug),
                $rank >= $facet->visible,
            );
        }

        return $values;
    }
}
