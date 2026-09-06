<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Enums\DisplayOrder;

/**
 * Turns a raw distribution into displayable values: engine order kept, labels
 * read from the taxonomy, everything past the visible limit folded away.
 */
final readonly class FacetValues
{
    public function __construct(private TermLabels $labels, private TermScope $scope) {}

    /**
     * @param  array<string, int>  $distribution  slug to count
     * @return list<FacetValue>
     */
    public function of(Facet $facet, array $distribution, ListingState $state): array
    {
        $distribution = $facet->within($distribution, $this->scope);
        $slugs = array_slice(array_keys($distribution), 0, $facet->cap);
        $labels = $this->labels->of($facet->taxonomy, $slugs);
        $values = [];

        foreach (array_values($slugs) as $rank => $slug) {
            $values[] = new FacetValue(
                $slug,
                $labels[$slug] ?? $slug,
                $distribution[$slug],
                $state->isSelected($facet->taxonomy, $slug),
                $rank >= $facet->visible,
            );
        }

        return $this->displayed($values, $facet);
    }

    /**
     * Folding is decided on the engine's order, then the values are shown in the
     * order the facet asked for: capping and reading are two different needs.
     *
     * @param  list<FacetValue>  $values
     * @return list<FacetValue>
     */
    private function displayed(array $values, Facet $facet): array
    {
        if ($facet->order === DisplayOrder::Count) {
            return $values;
        }

        usort($values, fn (FacetValue $a, FacetValue $b): int => strnatcasecmp($a->label, $b->label));

        return $values;
    }
}
