<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\ActiveValueList;

final class ActiveValues extends ListingComponent
{
    public function __construct(CurrentListing $listings, private readonly ActiveValueList $values, string $name = '')
    {
        parent::__construct($listings, $name);
    }

    public function render(): View
    {
        return view('meilifacets::components.active-values', [
            'values' => $this->values->of($this->listing),
        ]);
    }
}
