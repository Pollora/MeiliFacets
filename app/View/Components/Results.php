<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Seo\ItemList;
use Modules\MeiliFacets\View\CardSettings;

final class Results extends ListingComponent
{
    public function __construct(
        CurrentListing $listings,
        private readonly IndexingPolicy $indexing,
        private readonly CardSettings $cards,
        string $name = '',
    ) {
        parent::__construct($listings, $name);
    }

    public function priority(int $rank): ImagePriority
    {
        return ImagePriority::forRank($rank, $this->cards->eager);
    }

    /**
     * Nothing structured on a page search engines are told to skip — and the
     * list would go stale as soon as the client filters.
     */
    public function itemList(): ?ItemList
    {
        if ($this->indexing->isSecondaryView()) {
            return null;
        }

        $listing = $this->listing();

        return new ItemList($listing->cards(), $listing->offset());
    }

    public function render(): View
    {
        return view('meilifacets::components.results');
    }
}
