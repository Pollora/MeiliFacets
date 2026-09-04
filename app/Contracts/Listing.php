<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\Sort;

interface Listing
{
    public function name(): string;

    /**
     * @return list<Facet>
     */
    public function facets(): array;

    /**
     * @return array<string, Sort>
     */
    public function sorts(): array;

    /**
     * Filter clauses every query carries, resolved against the current request.
     *
     * @return list<string>
     */
    public function baseFilter(): array;

    public function perPage(): int;

    /**
     * Whether a ticked box searches at once or waits for a submit. A large
     * catalogue pays one search per tick, so the choice is per listing.
     */
    public function applyMode(): ApplyMode;
}
