<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Support\SiteLocale;
use Modules\MeiliFacets\View\CardSettings;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\ListingScript;

/** What the page needs beyond the listing itself: its script, its cards, its fallback. */
final class RenderingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Unavailable::class);
        $this->app->scoped(ListingScript::class);
        $this->app->scoped(CountLabel::class, fn (): CountLabel => new CountLabel(SiteLocale::current(...)));
        $this->app->bind(CardSettings::class, fn (): CardSettings => new CardSettings(
            (int) config('meilifacets.card.eager', CardSettings::DEFAULT_EAGER)
        ));
    }
}
