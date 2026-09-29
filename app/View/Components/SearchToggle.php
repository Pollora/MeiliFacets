<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

final class SearchToggle extends SearchComponent
{
    /** Where `php artisan module:publish MeiliFacets` puts the icon the toggle shows by default. */
    private const string DEFAULT_ICON = 'modules/meilifacets/images/search.svg';

    public function defaultIconUrl(): string
    {
        return asset(self::DEFAULT_ICON);
    }

    public function render(): View
    {
        return view('meilifacets::components.search-toggle', ['panelId' => $this->ids->searchPanel()]);
    }
}
