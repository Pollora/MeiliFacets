<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

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
        $count = $this->listing->activeFilterCount();

        return view('meilifacets::components.drawer-opener', [
            'drawerId' => $this->ids->drawer(),
            'countId' => $this->ids->drawerCount(),
            'badge' => $count === 0 ? '' : (string) $count,
            'holdsNothing' => $count === 0,
        ]);
    }
}
