<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\HeadingLevel;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class Drawer extends ListingComponent
{
    /** Written as is in the stylesheet: a media query cannot read a custom property. */
    public const string MOBILE = '(width < 48em)';

    public const int ROW_LIMIT = 5;

    public HeadingLevel $heading;

    public function __construct(
        CurrentListing $listings,
        #[Config('meilifacets.drawer.row_limit', self::ROW_LIMIT)] public int $rowLimit,
        string $name = '',
        public string $media = self::MOBILE,
        HeadingLevel|string $heading = HeadingLevel::H2,
    ) {
        parent::__construct($listings, $name);

        $this->heading = HeadingLevel::fromAttribute($heading);
    }

    public function render(): View
    {
        return view('meilifacets::components.listing.drawer', [
            'drawerId' => $this->ids->drawer(),
            'titleId' => $this->ids->drawerTitle(),
            'isSideSheet' => $this->listing->shownFilterCount() > $this->rowLimit,
        ]);
    }
}
