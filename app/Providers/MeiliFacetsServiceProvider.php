<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Indexing\DefaultCardProjector;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerceCardProjector;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Support\WooCommerce;
use Nwidart\Modules\Support\ModuleServiceProvider;

final class MeiliFacetsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'MeiliFacets';

    protected string $nameLower = 'meilifacets';

    public function register(): void
    {
        parent::register();

        $this->app->bind(TermHierarchy::class, WordPressTermHierarchy::class);

        // Resolved lazily: the module config is merged in boot(), not here.
        $this->app->bindIf(CardProjector::class, fn (): CardProjector => $this->defaultCard());
        $this->app->bind(IndexAttributes::class, fn (): IndexAttributes => $this->indexAttributes());
    }

    /**
     * The two methods below are the only place a plugin reaches the index.
     */
    private function indexAttributes(): IndexAttributes
    {
        return WooCommerce::isActive() ? new WooCommerceIndexAttributes : new EmptyIndexAttributes;
    }

    private function defaultCard(): CardProjector
    {
        // the module config win over the project one, and config:cache drops it.
        $card = new DefaultCardProjector(
            (string) config('meilifacets.card.image_size', DefaultCardProjector::DEFAULT_IMAGE_SIZE)
        );

        return WooCommerce::isActive() ? new WooCommerceCardProjector($card) : $card;
    }
}
