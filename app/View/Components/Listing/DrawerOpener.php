<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\Badge;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class DrawerOpener extends ListingComponent
{
    /** Where `php artisan module:publish MeiliFacets` puts the icon the opener shows by default. */
    private const string DEFAULT_ICON = 'modules/meilifacets/images/filters.svg';

    public function defaultIconUrl(): string
    {
        return asset(self::DEFAULT_ICON);
    }

    public function render(): View
    {
        return view('meilifacets::components.listing.drawer-opener', [
            'drawerId' => $this->ids->drawer(),
            'badge' => new Badge($this->ids->drawerCount(), $this->listing->activeFilterCount()),
        ]);
    }
}
