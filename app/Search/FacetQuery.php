<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;

final readonly class FacetQuery implements FilterQuery
{
    private const string COUNT = 'count:';

    public function __construct(private Facet $facet) {}

    public static function keyFor(string $taxonomy): string
    {
        return self::COUNT.$taxonomy;
    }

    public function key(): string
    {
        return self::keyFor($this->facet->taxonomy);
    }

    public function fields(): array
    {
        return [$this->facet->field()];
    }

    public function clause(ListingState $state): string
    {
        return FilterExpression::facet($this->facet, $state->selected($this->facet->taxonomy));
    }

    /**
     * A facet only needs a search of its own once it constrains the results;
     * until then the main response counts it correctly.
     */
    public function isMeasuredApart(ListingState $state): bool
    {
        return $this->facet->needsDisjunctiveCount() && $state->selected($this->facet->taxonomy) !== [];
    }
}
