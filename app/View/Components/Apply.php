<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\ApplyShape;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\Badge;

/**
 * Rendered in `submit`, where it searches, and seen everywhere. With `visible-in-drawer`, rendered in
 * `immediate` too: every box searches already, so it only closes the drawer, and is seen only while the
 * drawer is a sheet — never in the desktop row. The group renders it as a block.
 */
final class Apply extends ListingComponent
{
    public ApplyShape $shape;

    public function __construct(
        CurrentListing $listings,
        string $name = '',
        public bool $visibleInDrawer = false,
        ApplyShape|string $shape = ApplyShape::Pill,
    ) {
        parent::__construct($listings, $name);

        $this->shape = is_string($shape) ? ApplyShape::from($shape) : $shape;
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
        return view('meilifacets::components.apply', [
            'badge' => new Badge($this->ids->applyCount(), $this->listing->activeFilterCount()),
        ]);
    }
}
