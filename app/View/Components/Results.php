<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Http\RobotsPolicy;
use Modules\MeiliFacets\Seo\ItemList;

final class Results extends ListingComponent
{
    /**
     * Nothing structured on a page search engines are told to skip — and the
     * list would go stale as soon as the client filters.
     */
    public function itemList(): ?ItemList
    {
        if (app(RobotsPolicy::class)->appliesTo(request()->query())) {
            return null;
        }

        $resolved = $this->listing();

        return new ItemList($resolved->cards(), $resolved->pagination()->offset());
    }

    public function render(): View
    {
        return view('meilifacets::components.results');
    }
}
