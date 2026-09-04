<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Discovery;

use Modules\MeiliFacets\Contracts\Listing;

final class ListingRegistry
{
    /** @var array<string, Listing> */
    private array $listings = [];

    public function add(Listing $listing): void
    {
        $this->listings[$listing->name()] = $listing;
    }

    public function get(string $name): ?Listing
    {
        return $this->listings[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->listings);
    }
}
