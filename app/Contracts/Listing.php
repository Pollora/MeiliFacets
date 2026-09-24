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
     * Term facets only: what the engine can count by value.
     *
     * @return list<Facet>
     */
    public function facets(): array;

    /**
     * Everything a template may place, in declared order — facets and the price
     * range alike.
     *
     * @return list<Placeable>
     */
    public function filters(): array;

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

    /**
     * The text the page itself searches for — a search WordPress routed, served here.
     * Never what the visitor typed, which travels in the state.
     */
    public function baseQuery(): string;

    public function perPage(): int;

    /**
     * Whether a ticked box searches at once or waits for a submit. A large
     * catalogue pays one search per tick, so the choice is per listing.
     */
    public function applyMode(): ApplyMode;
}
