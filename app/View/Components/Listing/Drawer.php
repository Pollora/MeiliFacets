<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\HeadingLevel;
use Modules\MeiliFacets\Enums\RowOverflow;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class Drawer extends ListingComponent
{
    /** Written as is in the stylesheet: a media query cannot read a custom property. */
    public const string MOBILE = '(width < 48em)';

    public const int ROW_LIMIT = 5;

    public HeadingLevel $heading;

    private readonly RowOverflow $rowOverflow;

    private ?RowOverflow $decidedOverflow = null;

    private bool $isDecided = false;

    public function __construct(
        CurrentListing $listings,
        #[Config('meilifacets.drawer.row.limit', self::ROW_LIMIT)] public int $rowLimit,
        #[Config('meilifacets.drawer.row.overflow', RowOverflow::DEFAULT->value)] RowOverflow|string $rowOverflow,
        string $name = '',
        public string $media = self::MOBILE,
        HeadingLevel|string $heading = HeadingLevel::H2,
    ) {
        parent::__construct($listings, $name);

        $this->heading = HeadingLevel::fromAttribute($heading);
        $this->rowOverflow = RowOverflow::fromAttribute($rowOverflow);
    }

    /** Blade resolves the view before the slot: the count waits for the view, once the slot has placed its filters. */
    public function render(): View
    {
        $mark = $this->listing->placedFilterCount();

        if ($this->rowOverflow === RowOverflow::ShowMore) {
            $this->listing->startRow($this->rowLimit);
        }

        return view('meilifacets::components.listing.drawer', [
            'drawerId' => $this->ids->drawer(),
            'titleId' => $this->ids->drawerTitle(),
            'overflow' => fn (): ?string => $this->decidedOverflow($mark)?->value,
            'showsMore' => fn (): bool => $this->decidedOverflow($mark) === RowOverflow::ShowMore,
        ]);
    }

    private function decidedOverflow(int $mark): ?RowOverflow
    {
        if (! $this->isDecided) {
            $this->closeRow();
            $this->decidedOverflow = $this->listing->shownFilterCountSince($mark) > $this->rowLimit ? $this->rowOverflow : null;
            $this->isDecided = true;
        }

        return $this->decidedOverflow;
    }

    private function closeRow(): void
    {
        $this->listing->endRow();
    }
}
