<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Listing\Sort;

final readonly class SortChoices
{
    /** The engine's own order, and the only way back to it once a sort is picked. */
    private const string DEFAULT_VALUE = '';

    private const string DEFAULT_ID = 'default';

    public function __construct(private ElementId $ids) {}

    /**
     * @param  array<string, Sort>  $sorts
     * @param  array<string, int>  $matches  filtering sort to the hits it would keep
     * @return list<SortChoice>
     */
    public function of(array $sorts, ?string $current, array $matches): array
    {
        $choices = [$this->choice(self::DEFAULT_VALUE, __('Relevance'), $current)];

        foreach ($sorts as $value => $sort) {
            $choice = $this->choice($value, $sort->label, $current);
            $choices[] = $this->visible($choice, $sort, $matches[$value] ?? 0);
        }

        return $choices;
    }

    private function choice(string $value, string $label, ?string $current): SortChoice
    {
        return new SortChoice(
            $value,
            $label,
            $this->ids->sortOption($value === self::DEFAULT_VALUE ? self::DEFAULT_ID : $value),
            $value === ($current ?? self::DEFAULT_VALUE),
        );
    }

    private function visible(SortChoice $choice, Sort $sort, int $matches): SortChoice
    {
        if ($choice->selected) {
            return $choice;
        }

        $matchesNothing = $sort->isFiltering() && $matches === 0;

        return $matchesNothing ? $choice->hide() : $choice;
    }
}
