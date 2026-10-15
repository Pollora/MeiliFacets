<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use BackedEnum;
use Illuminate\Support\Facades\View;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\View\ElementId;

abstract class ListingComponent extends ContractComponent
{
    /** The name of `$listing`: Laravel hands a component's public properties to the components it holds. */
    private const string ENCLOSING = 'listing';

    public ResolvedListing $listing;

    public ElementId $ids;

    public function __construct(CurrentListing $listings, public string $name = '', bool $scroll = false)
    {
        $this->scroll = $scroll;
        $this->listing = $name === '' ? $this->enclosingOrOnly($listings) : $listings->named($name);
        $this->ids = new ElementId($this->listing->name());
    }

    private function enclosingOrOnly(CurrentListing $listings): ResolvedListing
    {
        $enclosing = View::getConsumableComponentData(self::ENCLOSING);

        return $enclosing instanceof ResolvedListing ? $enclosing : $listings->onlyOne();
    }

    /** A project names its filters in an enum; the listing looks them up by string. */
    protected function designated(Placeable|BackedEnum|string $filter): Placeable|string
    {
        return $filter instanceof BackedEnum ? (string) $filter->value : $filter;
    }
}
