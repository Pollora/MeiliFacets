<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\DefaultTerms;
use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Contracts\ValueOrder;
use Modules\MeiliFacets\Enums\DefaultTerm;
use Modules\MeiliFacets\Enums\DisplayOrder;

final readonly class FacetValues
{
    public function __construct(
        private TermLabels $labels,
        private TermScope $scope,
        private DefaultTerms $defaults,
    ) {}

    /**
     * @param  array<string, int>  $distribution  slug to count
     * @param  array<string, int>|null  $unfiltered  slug to count before any visitor filter, null when nothing narrowed the listing
     * @return list<FacetValue>
     */
    public function of(Facet $facet, array $distribution, ListingState $state, ?array $unfiltered = null): array
    {
        $counts = $this->inScope($facet, $distribution);
        $offered = $unfiltered === null ? [] : $this->inScope($facet, $unfiltered);
        $slugs = $this->shown($facet, $offered, $counts, $state);
        $labels = $this->labels->of($facet->taxonomy, $slugs);
        $values = [];

        foreach ($slugs as $slug) {
            $values[] = new FacetValue(
                $slug,
                $labels[$slug] ?? $slug,
                $counts[$slug] ?? 0,
                $state->isSelected($facet->taxonomy, $slug),
                false,
            );
        }

        return $this->folded($this->displayed($values, $facet, array_keys($labels)), $facet);
    }

    /**
     * @param  array<string, int>  $distribution
     * @return array<string, int>
     */
    private function inScope(Facet $facet, array $distribution): array
    {
        return $facet->within($this->browsable($facet, $distribution), $this->scope);
    }

    /**
     * @param  array<string, int>  $offered
     * @param  array<string, int>  $counts
     * @return list<string>
     */
    private function shown(Facet $facet, array $offered, array $counts, ListingState $state): array
    {
        $held = array_filter(
            $state->selected($facet->taxonomy),
            static fn (string $slug): bool => isset($offered[$slug]) || isset($counts[$slug]),
        );

        return array_values(array_unique([
            ...array_slice(array_keys($offered), 0, $facet->cap),
            ...array_slice(array_keys($counts), 0, $facet->cap),
            ...$held,
        ]));
    }

    /**
     * @param  list<FacetValue>  $values
     * @return list<FacetValue>
     */
    private function folded(array $values, Facet $facet): array
    {
        $displayed = [];
        $rank = 0;

        foreach ($values as $value) {
            $hidden = ! $value->selected && ($value->count === 0 || $rank >= $facet->visible);
            $rank += $value->count > 0 ? 1 : 0;

            $displayed[] = $hidden ? new FacetValue($value->slug, $value->label, $value->count, $value->selected, true) : $value;
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

        return $order === DisplayOrder::Declared ? $this->following($declared) : null;
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
