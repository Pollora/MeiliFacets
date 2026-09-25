<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\CurrentListing;

/**
 * Rendered in `submit`, where it searches, and seen everywhere. With `visible-in-drawer`, rendered in
 * `immediate` too: every box searches already, so it only closes the drawer, and is seen only while the
 * drawer is a sheet — never in the desktop row.
 */
final class Apply extends ListingComponent
{
    public function __construct(CurrentListing $listings, string $name = '', public bool $visibleInDrawer = false)
    {
        parent::__construct($listings, $name);
    }

    public function shouldRender(): bool
    {
        return $this->visibleInDrawer || $this->listing->applyMode()->needsButton();
    }

    public function onlyInSheet(): bool
    {
        return ! $this->listing->applyMode()->needsButton();
    }

    public function render(): View
    {
        $count = $this->listing->activeFilterCount();

        return view('meilifacets::components.apply', [
            'countId' => $this->ids->applyCount(),
            'badge' => $count === 0 ? '' : (string) $count,
            'holdsNothing' => $count === 0,
        ]);
    }
}
