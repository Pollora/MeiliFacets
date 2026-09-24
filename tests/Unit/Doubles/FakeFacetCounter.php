<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Listing\ListingState;

final readonly class FakeFacetCounter implements FacetCounter
{
    /**
     * @param  array<string, array<string, mixed>>  $queries
     */
    public function __construct(private array $queries = []) {}

    public function queries(Listing $listing, ListingState $state): array
    {
        return $this->queries;
    }
}
