<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Collator;
use Illuminate\Support\ServiceProvider;
use IntlException;
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
use Modules\MeiliFacets\Support\UrlParameters;

/** What a listing is made of: its terms, the order it reads them in, and the registry that holds it. */
final class ListingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermLabels::class, WordPressTermLabels::class);
        $this->app->scoped(TermScope::class, WordPressTermScope::class);
        $this->app->scoped(DefaultTerms::class, WordPressDefaultTerms::class);

        $this->app->scoped(NameOrder::class, fn (): NameOrder => new NameOrder($this->collator()));

        // Unguarded: `ProductListing` is their only consumer, and it refuses itself without WooCommerce.
        $this->app->scopedIf(ProductFacets::class, WooCommerceFacets::class);
        $this->app->scopedIf(ProductSorts::class, WooCommerceSorts::class);

        $this->app->singleton(ListingRegistry::class);
        $this->app->scoped(CurrentListing::class);
        $this->app->bind(UrlParameters::class, UrlParameters::fromConfig(...));
    }

    /**
     * Read on resolution rather than on registration: Polylang sets the language on a
     * later hook, and an empty locale collates as `en_US_POSIX`, not as the site's.
     */
    private function collator(): ?Collator
    {
        if (! class_exists(Collator::class)) {
            return null;
        }

        $locale = function_exists('get_locale') ? get_locale() : $this->app->getLocale();

        try {
            $collator = new Collator($locale);
        } catch (IntlException) {
            return null;
        }

        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);

        return $collator;
    }
}
