<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

/** A listing whose items follow an order of their own while no sort is chosen and no term is searched. */
interface DefaultSortedListing extends Listing
{
    /**
     * Meilisearch sort expressions; an empty list keeps the engine's order.
     *
     * @return list<string>
     */
    public function defaultSort(): array;
}
