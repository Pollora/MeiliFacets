<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class Pagination extends ListingComponent
{
    private ?string $linkedQuery = null;

    public function __construct(
        CurrentListing $listings,
        private readonly PageAddress $address,
        string $name = '',
        bool $scroll = false,
    ) {
        parent::__construct($listings, $name, $scroll);
    }

    public function linkTo(int $page): string
    {
        return $this->address->pathOf($page).$this->linkedQuery();
    }

    /** One query for every link of the render. */
    private function linkedQuery(): string
    {
        return $this->linkedQuery ??= $this->address->linkedQuery();
    }

    public function render(): View
    {
        return view('meilifacets::components.listing.pagination', ['pagination' => $this->listing->pagination()]);
    }
}
