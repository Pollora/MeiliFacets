<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Contracts\PageMetadata;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Http\ServiceUnavailableHeaders;
use Modules\MeiliFacets\Support\SiteLocale;
use Modules\MeiliFacets\Support\YoastSeo;
use Modules\MeiliFacets\View\CardSettings;
use Modules\MeiliFacets\View\ClientScript;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\WordPressPageMetadata;
use Modules\MeiliFacets\View\YoastPageMetadata;

/** What the page needs beyond the listing itself: its script, its cards, its fallback. */
final class RenderingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ServiceUnavailable::class);
        $this->app->scoped(ListingPage::class);
        $this->app->scoped(CurrentListing::class);
        $this->app->scoped(ClientScript::class);
        $this->app->scoped(CountLabel::class, fn (): CountLabel => new CountLabel(SiteLocale::current(...)));
        // Resolved with the listing, once WordPress has loaded its plugins.
        $this->app->bindIf(PageMetadata::class, fn (): PageMetadata => YoastSeo::isActive()
            ? $this->app->make(YoastPageMetadata::class)
            : $this->app->make(WordPressPageMetadata::class));
        $this->app->bind(CardSettings::class, fn (): CardSettings => new CardSettings(
            (int) config('meilifacets.card.eager', CardSettings::DEFAULT_EAGER)
        ));
    }

    public function boot(): void
    {
        $kernel = $this->app->make(Kernel::class);

        if ($kernel instanceof HttpKernel) {
            $kernel->pushMiddleware(ServiceUnavailableHeaders::class);
        }
    }
}
