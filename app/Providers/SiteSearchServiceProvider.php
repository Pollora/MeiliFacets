<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Indexing\WooCommerceProductFields;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\SearchablePostTypes;
use Modules\MeiliFacets\SiteSearch\SearchableTypeFactory;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;
use Modules\MeiliFacets\SiteSearch\WordPressSearchableTypes;
use Modules\MeiliFacets\Support\WooCommerce;

final class SiteSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(WordPressSearchableTypes::class);
        $this->app->scoped(WooCommerceSearchableTypes::class, $this->wooCommerce(...));
        $this->app->scopedIf(SearchableTypes::class, WooCommerceSearchableTypes::class);

        $this->app->scoped(AcceptedSearchTypes::class);
    }

    private function wooCommerce(): WooCommerceSearchableTypes
    {
        return new WooCommerceSearchableTypes(
            $this->app->make(WordPressSearchableTypes::class),
            $this->app->make(SearchablePostTypes::class),
            $this->app->make(SearchableTypeFactory::class),
            $this->app->make(WooCommerceProductFields::class),
            WooCommerce::isActive(...),
        );
    }
}
