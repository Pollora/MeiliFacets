<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;

final readonly class PriceQuery implements FilterQuery
{
    public const string KEY = 'bounds';

    public function __construct(private PriceFilter $filter) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function fields(): array
    {
        return $this->filter->fields();
    }

    public function clause(ListingState $state): string
    {
        return FilterExpression::overlapping($state->price);
    }

    /** Lifting the price costs a search, so it is only asked when a range is held. */
    public function isMeasuredApart(ListingState $state): bool
    {
        return ! $state->price->isEmpty();
    }
}
