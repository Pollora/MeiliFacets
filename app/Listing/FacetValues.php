<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\DefaultTerms;
use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Contracts\ValueOrder;
use Modules\MeiliFacets\Enums\DefaultTerm;
use Modules\MeiliFacets\Enums\DisplayOrder;

/**
 * Turns a raw distribution into displayable values: engine order kept, labels
 * read from the taxonomy, everything past the visible limit folded away.
 */
final readonly class FacetValues
{
    public function __construct(
        private TermLabels $labels,
        private TermScope $scope,
        private DefaultTerms $defaults,
        private NameOrder $names,
    ) {}

    /**
     * @param  array<string, int>  $distribution  slug to count
     * @return list<FacetValue>
     */
    public function of(Facet $facet, array $distribution, ListingState $state): array
    {
        $distribution = $facet->within($this->browsable($facet, $distribution), $this->scope);
        $slugs = array_slice(array_keys($distribution), 0, $facet->cap);
        $labels = $this->labels->of($facet->taxonomy, $slugs);
        $values = [];

        foreach ($slugs as $slug) {
            $values[] = new FacetValue(
                $slug,
                $labels[$slug] ?? $slug,
                $distribution[$slug],
                $state->isSelected($facet->taxonomy, $slug),
                false,
            );
        }

        return $this->folded($this->displayed($values, $facet, array_keys($labels)), $facet);
    }

    /**
     * The visitor reads the first values of the order the facet declared. The
     * engine's count decides which values survive the cap, never which are read.
     *
     * @param  list<FacetValue>  $values
     * @return list<FacetValue>
     */
    private function folded(array $values, Facet $facet): array
    {
        $displayed = [];

        foreach ($values as $rank => $value) {
            $displayed[] = $rank < $facet->visible || $value->selected
                ? $value
                : new FacetValue($value->slug, $value->label, $value->count, $value->selected, true);
        }

        return $displayed;
    }

    /**
     * @param  array<string, int>  $distribution
     * @return array<string, int>
     */
    private function browsable(Facet $facet, array $distribution): array
    {
        if ($facet->defaultTerm === DefaultTerm::Shown) {
            return $distribution;
        }

        $fallback = $this->defaults->slugOf($facet->taxonomy);

        return $fallback === null ? $distribution : array_diff_key($distribution, [$fallback => 0]);
    }

    /**
     * Folding is decided on the engine's order, then the values are shown in the
     * order the facet asked for: capping and reading are two different needs.
     *
     * @param  list<FacetValue>  $values
     * @param  list<string>  $declared  slugs as the taxonomy lists them
     * @return list<FacetValue>
     */
    private function displayed(array $values, Facet $facet, array $declared): array
    {
        $comparison = $this->comparing($facet->order, $declared);

        if ($comparison !== null) {
            usort($values, $comparison);
        }

        return $values;
    }

    /**
     * @param  list<string>  $declared
     * @return null|callable(FacetValue, FacetValue): int null leaves the engine's order alone
     */
    private function comparing(DisplayOrder|ValueOrder $order, array $declared): ?callable
    {
        if ($order instanceof ValueOrder) {
            return $order->compare(...);
        }

        return match ($order) {
            DisplayOrder::Count => null,
            DisplayOrder::Name => $this->names->compare(...),
            DisplayOrder::Declared => $this->following($declared),
        };
    }

    /**
     * @param  list<string>  $declared
     * @return callable(FacetValue, FacetValue): int
     */
    private function following(array $declared): callable
    {
        $rank = array_flip($declared);

        // A slug the taxonomy no longer lists has no place in it: it waits at the end.
        return static fn (FacetValue $a, FacetValue $b): int => ($rank[$a->slug] ?? PHP_INT_MAX) <=> ($rank[$b->slug] ?? PHP_INT_MAX);
    }
}
