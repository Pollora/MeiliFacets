<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\SortWidget;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\Disclosure;
use Modules\MeiliFacets\View\SortChoice;
use Modules\MeiliFacets\View\SortChoices;
use Modules\MeiliFacets\View\SortSummary;

final class Sort extends ListingComponent
{
    /** @var list<SortChoice> */
    public array $choices;

    public SortChoice $selected;

    public SortSummary $summary;

    public SortWidget $widget;

    public function __construct(
        CurrentListing $listings,
        string $name = '',
        bool $scroll = false,
        SortWidget|string $widget = SortWidget::Listbox,
        public bool $collapsible = false,
    ) {
        parent::__construct($listings, $name, $scroll);

        $this->widget = is_string($widget) ? SortWidget::from($widget) : $widget;

        $this->listing->placeSort();

        $this->choices = new SortChoices($this->ids)->of(
            $this->listing->sorts(),
            $this->listing->currentSort(),
            $this->listing->sortMatches(),
        );
        $this->selected = $this->currentChoice();
        $this->summary = SortSummary::of($this->selected->label);
    }

    public function render(): View
    {
        return view(match ($this->widget) {
            SortWidget::Listbox => 'meilifacets::components.sort',
            SortWidget::Radios => 'meilifacets::components.sort-radios',
        });
    }

    public function disclosure(): Disclosure
    {
        return new Disclosure($this->summary->label, $this->ids->sortPanel());
    }

    public function panelId(): string
    {
        return $this->ids->sortPanel();
    }

    public function choiceName(): string
    {
        return $this->ids->sortChoiceName();
    }

    private function currentChoice(): SortChoice
    {
        return array_find($this->choices, static fn (SortChoice $choice): bool => $choice->selected)
            ?? $this->choices[0];
    }
}
