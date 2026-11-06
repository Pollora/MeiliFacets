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
    /** Laravel answers with the nearest enclosing component carrying the name, whatever it holds. */
    private const string ENCLOSING = 'meilifacetsListing';

    public ResolvedListing $listing;

    public ResolvedListing $meilifacetsListing;

    public ElementId $ids;

    public function __construct(CurrentListing $listings, public string $name = '', bool $scroll = false)
    {
        $this->scroll = $scroll;
        $this->listing = $name === '' ? $this->enclosingOrOnly($listings) : $listings->named($name);
        $this->meilifacetsListing = $this->listing;
        $this->ids = new ElementId($this->listing->name());
    }

    private function enclosingOrOnly(CurrentListing $listings): ResolvedListing
    {
        $enclosing = View::getConsumableComponentData(self::ENCLOSING);

        return $enclosing instanceof ResolvedListing ? $enclosing : $listings->onlyOne();
    }

    /**
     * A declaration from the group, or a name a template placed on its own: a project names its filters in an enum.
     *
     * @template T of Placeable
     *
     * @param  class-string<T>  $kind
     * @return T
     */
    protected function place(Placeable|BackedEnum|string $filter, string $kind): Placeable
    {
        $placement = $this->listing->placement();

        if ($filter instanceof Placeable) {
            return $placement->placeFilter($filter, $kind);
        }

        return $placement->placeNamed($filter instanceof BackedEnum ? (string) $filter->value : $filter, $kind);
    }
}
