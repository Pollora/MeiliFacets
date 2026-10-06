<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Indexing\ConfiguredIndexAttributes;
use Modules\MeiliFacets\Indexing\DefaultCardProjector;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;
use Modules\MeiliFacets\Indexing\DeferredCardProjector;
use Modules\MeiliFacets\Indexing\DeferredIndexAttributes;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\EmptyVariantFields;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Modules\MeiliFacets\Indexing\PostText;
use Modules\MeiliFacets\Indexing\SummaryCardProjector;
use Modules\MeiliFacets\Indexing\VariationChanges;
use Modules\MeiliFacets\Indexing\VariationMetaRewrites;
use Modules\MeiliFacets\Indexing\WooCommerceCardProjector;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerceProductFields;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Support\WooCommerce;

/** What the document carries, and who contributes to it. */
final class IndexingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermHierarchy::class, WordPressTermHierarchy::class);

        // A hook can resolve these before WordPress loads its plugins: WooCommerce is asked on every call.
        $this->app->bind(IndexAttributes::class, fn (): IndexAttributes => $this->attributes());
        $this->app->bindIf(CardProjector::class, fn (): CardProjector => $this->card());
        $this->app->bindIf(VariantFields::class, EmptyVariantFields::class);

        $this->app->scoped(IndexedTaxonomies::class);
        $this->app->scoped(FacetedPostIndexable::class);
        // Hooks discovered on them share what they remembered during the request.
        $this->app->singleton(VariationChanges::class);
        $this->app->singleton(VariationMetaRewrites::class);
        $this->app->bind(
            WooCommerceProductFields::class,
            fn (): WooCommerceProductFields => new WooCommerceProductFields(WooCommerce::isActive(...))
        );
        $this->app->scopedIf(SearchableAttributes::class, DefaultSearchableAttributes::class);
    }

    private function attributes(): IndexAttributes
    {
        $plugin = new DeferredIndexAttributes(
            WooCommerce::isActive(...),
            new WooCommerceIndexAttributes,
            new EmptyIndexAttributes
        );

        return new ConfiguredIndexAttributes($plugin, (array) config('meilifacets.displayed_attributes', []));
    }

    private function card(): CardProjector
    {
        $card = $this->defaultCard(DefaultCardProjector::DEFAULT_IMAGE_SIZE);
        $summaryCard = new SummaryCardProjector($card, new PostText);
        $productCard = $this->defaultCard(WooCommerceCardProjector::DEFAULT_IMAGE_SIZE);

        return new DeferredCardProjector(
            WooCommerce::isActive(...),
            new WooCommerceCardProjector($productCard, $summaryCard),
            $summaryCard
        );
    }

    private function defaultCard(string $defaultImageSize): DefaultCardProjector
    {
        return new DefaultCardProjector((string) config('meilifacets.card.image_size', $defaultImageSize));
    }
}
