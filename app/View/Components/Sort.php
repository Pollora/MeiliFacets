<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\SortChoice;
use Modules\MeiliFacets\View\SortChoices;

final class Sort extends ListingComponent
{
    /** @var list<SortChoice>|null */
    private ?array $choices = null;

    /**
     * @return list<SortChoice>
     */
    public function choices(): array
    {
        return $this->choices ??= $this->build();
    }

    public function selected(): SortChoice
    {
        $choices = $this->choices();

        return array_find($choices, fn (SortChoice $choice): bool => $choice->selected) ?? $choices[0];
    }

    public function id(): string
    {
        return $this->ids()->sort();
    }

    public function render(): View
    {
        return view('meilifacets::components.sort');
    }

    /**
     * @return list<SortChoice>
     */
    private function build(): array
    {
        $listing = $this->listing();

        return (new SortChoices($this->id()))->of($listing->sorts(), $listing->currentSort());
    }
}
