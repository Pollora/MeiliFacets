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
     * @return list<SortChoice>
     */
    public function of(array $sorts, ?string $current): array
    {
        $choices = [$this->choice(self::DEFAULT_VALUE, __('Relevance'), $current)];

        foreach ($sorts as $value => $sort) {
            $choices[] = $this->choice($value, $sort->label, $current);
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
}
