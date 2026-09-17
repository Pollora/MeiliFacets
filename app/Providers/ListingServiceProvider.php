<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Contracts\DefaultTerms;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Listing\WordPressDefaultTerms;
use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Listing\WordPressTermScope;
use Modules\MeiliFacets\Support\SiteCollator;
use Modules\MeiliFacets\Support\UrlParameters;

/** What a listing is made of: its terms, the order it reads them in, and the registry that holds it. */
final class ListingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermLabels::class, WordPressTermLabels::class);
        $this->app->scoped(TermScope::class, WordPressTermScope::class);
        $this->app->scoped(DefaultTerms::class, WordPressDefaultTerms::class);

        $this->app->scoped(SiteCollator::class);
        $this->app->scoped(NameOrder::class, fn (): NameOrder => new NameOrder($this->app->make(SiteCollator::class)->current(...)));

        // Unguarded: `ProductListing` is their only consumer, and it refuses itself without WooCommerce.
        $this->app->scopedIf(ProductFacets::class, WooCommerceFacets::class);
        $this->app->scopedIf(ProductSorts::class, WooCommerceSorts::class);

        $this->app->singleton(ListingRegistry::class);
        $this->app->scoped(CurrentListing::class);
        $this->app->bind(UrlParameters::class, UrlParameters::fromConfig(...));
    }
}
