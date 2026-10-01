<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Discovery;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Support\NamedRegistry;

/**
 * @extends NamedRegistry<Listing>
 */
final class ListingRegistry extends NamedRegistry
{
    /** Idempotent: Pollora re-applies every discovery when a plugin registers itself. */
    public function add(Listing $listing): void
    {
        $this->entries[$listing->name()] = $listing;
    }

    protected function kind(): string
    {
        return 'listing';
    }
}
