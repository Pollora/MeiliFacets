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

    /** Carries the listing name: a template may place two listings on one page. */
    public function id(): string
    {
        return 'meilifacets-'.$this->name.'-sort';
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
        $resolved = $this->listing();

        return (new SortChoices($this->id()))->of($resolved->listing->sorts(), $resolved->state->sort);
    }
}
