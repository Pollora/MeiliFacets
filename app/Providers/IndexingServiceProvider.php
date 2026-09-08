<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Indexing\ConfiguredIndexAttributes;
use Modules\MeiliFacets\Indexing\DefaultCardProjector;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerceCardProjector;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Support\WooCommerce;

/** What the document carries, and who contributes to it. */
final class IndexingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermHierarchy::class, WordPressTermHierarchy::class);

        // WordPress has not loaded its plugins here: `wc_get_product` does not exist yet, so the
        // WooCommerce guard belongs inside the closure, never around the binding.
        $this->app->bind(IndexAttributes::class, fn (): IndexAttributes => $this->attributes());
        $this->app->bindIf(CardProjector::class, fn (): CardProjector => $this->card());
    }

    /** The two methods below are the only place a plugin reaches the index. */
    private function attributes(): IndexAttributes
    {
        $plugin = WooCommerce::isActive() ? new WooCommerceIndexAttributes : new EmptyIndexAttributes;

        return new ConfiguredIndexAttributes($plugin, (array) config('meilifacets.displayed_attributes', []));
    }

    private function card(): CardProjector
    {
        $card = new DefaultCardProjector(
            (string) config('meilifacets.card.image_size', DefaultCardProjector::DEFAULT_IMAGE_SIZE)
        );

        return WooCommerce::isActive() ? new WooCommerceCardProjector($card) : $card;
    }
}
