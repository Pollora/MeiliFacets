<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Enums\Stylesheet;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\ClientScript;
use Modules\MeiliFacets\View\ClientStylesheet;
use Modules\MeiliFacets\View\ListingDescription;

final class Listing extends ListingComponent
{
    public function __construct(
        CurrentListing $listings,
        ClientStylesheet $stylesheet,
        ClientScript $script,
        ListingDescription $description,
        ListingPage $page,
        string $name = '',
    ) {
        parent::__construct($listings, $name);

        $page->markAsCurrent();

        $stylesheet->require(Stylesheet::Listing);
        $script->require(ScriptModule::Listing, $this->listing->name(), fn (): array => $description->of($this->listing));
    }

    public function render(): View
    {
        return view('meilifacets::components.listing', ['contract' => Contract::version()]);
    }
}
