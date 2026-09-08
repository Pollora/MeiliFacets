<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\SortChoice;
use Modules\MeiliFacets\View\SortChoices;

final class Sort extends ListingComponent
{
    /** @var list<SortChoice> */
    public array $choices;

    public SortChoice $selected;

    public function __construct(CurrentListing $listings, string $name = '', bool $scroll = false)
    {
        parent::__construct($listings, $name, $scroll);

        $this->choices = new SortChoices($this->ids)->of($this->listing->sorts(), $this->listing->currentSort());
        $this->selected = $this->currentChoice();
    }

    public function render(): View
    {
        return view('meilifacets::components.sort');
    }

    private function currentChoice(): SortChoice
    {
        return array_find($this->choices, static fn (SortChoice $choice): bool => $choice->selected)
            ?? $this->choices[0];
    }
}
