<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\RowOverflow;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class Drawer extends ListingComponent
{
    /** Written as is in the stylesheet: a media query cannot read a custom property. */
    public const string MOBILE = '(width < 48em)';

    public const int ROW_LIMIT = 5;

    private readonly RowOverflow $rowOverflow;

    private ?RowOverflow $decidedOverflow = null;

    private bool $isDecided = false;

    private bool $hasMoreToShow = false;

    public function __construct(
        CurrentListing $listings,
        #[Config('meilifacets.drawer.row.limit', self::ROW_LIMIT)] public int $rowLimit,
        #[Config('meilifacets.drawer.row.overflow', RowOverflow::DEFAULT->value)] RowOverflow|string $rowOverflow,
        string $name = '',
        public string $media = self::MOBILE,
    ) {
        parent::__construct($listings, $name);

        $this->rowOverflow = RowOverflow::fromAttribute($rowOverflow);
    }

    /** Blade resolves the view before the slot: the count waits for the view, once the slot has placed its filters. */
    public function render(): View
    {
        $mark = $this->listing->placement()->placedFilterCount();

        if ($this->rowOverflow === RowOverflow::ShowMore) {
            $this->listing->placement()->startRow($this->rowLimit);
        }

        return view('meilifacets::components.listing.drawer', [
            'drawerId' => $this->ids->drawer(),
            'titleId' => $this->ids->drawerTitle(),
            'overflow' => fn (): ?string => $this->decidedOverflow($mark)?->value,
            'showsMore' => fn (): bool => $this->decidedOverflow($mark) === RowOverflow::ShowMore,
            'hasMoreToShow' => fn (): bool => $this->decidedOverflow($mark) instanceof RowOverflow && $this->hasMoreToShow,
        ]);
    }

    private function decidedOverflow(int $mark): ?RowOverflow
    {
        if (! $this->isDecided) {
            $placement = $this->listing->placement();

            // Read while the row is open: closed, no filter is past it any more.
            $this->hasMoreToShow = array_any($placement->filtersBeyondRow(), $this->listing->isShown(...));
            $placement->endRow();
            $this->decidedOverflow = $placement->catalogFilterCountSince($mark) > $this->rowLimit ? $this->rowOverflow : null;
            $this->isDecided = true;
        }

        return $this->decidedOverflow;
    }
}
