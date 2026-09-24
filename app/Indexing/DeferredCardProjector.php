<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Contracts\CardProjector;
use WP_Post;

/**
 * Asks whether the plugin is loaded on every card, not when the index is wired.
 */
final readonly class DeferredCardProjector implements CardProjector
{
    /**
     * @param  Closure(): bool  $pluginIsActive
     */
    public function __construct(
        private Closure $pluginIsActive,
        private CardProjector $plugin,
        private CardProjector $withoutPlugin,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array
    {
        $projector = ($this->pluginIsActive)() ? $this->plugin : $this->withoutPlugin;

        return $projector->project($post);
    }
}
