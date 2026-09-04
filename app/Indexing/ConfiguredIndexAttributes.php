<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\IndexAttributes;

/**
 * Adds what the project declared in configuration to what a plugin contributes.
 */
final readonly class ConfiguredIndexAttributes implements IndexAttributes
{
    /**
     * @param  list<string>  $displayed
     */
    public function __construct(private IndexAttributes $attributes, private array $displayed) {}

    /**
     * @return list<string>
     */
    public function filterable(): array
    {
        return $this->attributes->filterable();
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return $this->attributes->sortable();
    }

    /**
     * @return list<string>
     */
    public function displayed(): array
    {
        return [...$this->attributes->displayed(), ...$this->displayed];
    }
}
