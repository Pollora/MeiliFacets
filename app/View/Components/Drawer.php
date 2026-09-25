<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\HeadingLevel;
use Modules\MeiliFacets\Listing\CurrentListing;

final class Drawer extends ListingComponent
{
    /** Written as is in the stylesheet: a media query cannot read a custom property. */
    public const string MOBILE = '(width < 48em)';

    public HeadingLevel $heading;

    public function __construct(
        CurrentListing $listings,
        string $name = '',
        public string $media = self::MOBILE,
        HeadingLevel|string $heading = HeadingLevel::H2,
    ) {
        parent::__construct($listings, $name);

        $this->heading = is_string($heading) ? HeadingLevel::from($heading) : $heading;
    }

    public function render(): View
    {
        return view('meilifacets::components.drawer', [
            'drawerId' => $this->ids->drawer(),
            'titleId' => $this->ids->drawerTitle(),
        ]);
    }
}
