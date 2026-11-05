<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Contracts\IndexAttributes;

/**
 * Asks whether the plugin is loaded on every read, not when the index is wired.
 */
final readonly class DeferredIndexAttributes implements IndexAttributes
{
    /**
     * @param  Closure(): bool  $pluginIsActive
     */
    public function __construct(
        private Closure $pluginIsActive,
        private IndexAttributes $plugin,
        private IndexAttributes $withoutPlugin,
    ) {}

    public function exactlyMatched(): array
    {
        return $this->current()->exactlyMatched();
    }

    public function filterable(): array
    {
        return $this->current()->filterable();
    }

    public function sortable(): array
    {
        return $this->current()->sortable();
    }

    public function displayed(): array
    {
        return $this->current()->displayed();
    }

    private function current(): IndexAttributes
    {
        return ($this->pluginIsActive)() ? $this->plugin : $this->withoutPlugin;
    }
}
