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
    /** @var list<array<string, mixed>> */
    public array $cards;

    /** Null on a page search engines are told to skip: nothing structured to publish. */
    public ?ItemList $items;

    public function __construct(
        CurrentListing $listings,
        IndexingPolicy $indexing,
        private readonly CardSettings $cardSettings,
        string $name = '',
    ) {
        parent::__construct($listings, $name);

        $this->cards = $this->listing->cards();
        $this->items = $indexing->isSecondaryView()
            ? null
            : new ItemList($this->cards, $this->listing->offset());
    }

    public function priority(int $rank): ImagePriority
    {
        return ImagePriority::forRank($rank, $this->cardSettings->eager);
    }

    public function render(): View
    {
        return view('meilifacets::components.results');
    }
}
