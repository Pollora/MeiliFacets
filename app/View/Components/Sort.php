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
    /** A private-use character: no label holds one, so the translated sentence splits around it. */
    private const string CHOICE_MARKER = "\u{E000}";

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

        $this->widget = SortWidget::fromAttribute($widget);

        $this->listing->placeSort();

        $this->choices = new SortChoices($this->ids)->of(
            $this->listing->sorts(),
            $this->listing->currentSort(),
            $this->listing->sortMatches(),
        );
        $this->selected = $this->currentChoice();
        $this->summary = $this->summaryOf($this->selected->label);
    }

    /** A single order leaves nothing to choose. */
    public function shouldRender(): bool
    {
        return count($this->choices) > 1;
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
        return Disclosure::restatedInItsSlot($this->summary->label, $this->ids->sortPanel());
    }

    public function panelId(): string
    {
        return $this->ids->sortPanel();
    }

    public function choiceName(): string
    {
        return $this->ids->sortChoiceName();
    }

    private function summaryOf(string $choice): SortSummary
    {
        $sentence = __('Sort by: :choice', ['choice' => self::CHOICE_MARKER]);
        [$lead, $trail] = explode(self::CHOICE_MARKER, $sentence, 2) + [1 => ''];

        return new SortSummary(__('Sort by'), $lead, $choice, $trail);
    }

    private function currentChoice(): SortChoice
    {
        return array_find($this->choices, static fn (SortChoice $choice): bool => $choice->selected)
            ?? $this->choices[0];
    }
}
