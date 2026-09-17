<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Listing\ListingState;

interface FilterQuery
{
    public function key(): string;

    /**
     * @return list<string>
     */
    public function fields(): array;

    public function clause(ListingState $state): string;

    public function isMeasuredApart(ListingState $state): bool;
}
