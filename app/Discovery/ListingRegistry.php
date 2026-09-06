<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Discovery;

use Modules\MeiliFacets\Contracts\Listing;
use RuntimeException;

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
     * The listing a template means when it names none. Ambiguity is refused
     * rather than guessed: a second listing changes what the first one shows.
     */
    public function sole(): Listing
    {
        return count($this->listings) === 1
            ? reset($this->listings)
            : throw new RuntimeException(
                'Name the listing: '.(
                    $this->listings === []
                        ? 'none is declared.'
                        : count($this->listings).' are declared ('.implode(', ', $this->names()).').'
                )
            );
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->listings);
    }
}
