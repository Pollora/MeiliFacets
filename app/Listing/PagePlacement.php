<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\PlacedControl;
use RuntimeException;

final class PagePlacement
{
    /** @var array<value-of<PlacedControl>, array<string, true>> */
    private array $rendered = [];

    /** @var array<string, true> facet names a template placed on their own */
    private array $apart = [];

    public function __construct(private readonly string $listing) {}

    public function place(Placeable $facet): void
    {
        if ($this->isRendered(PlacedControl::Facet, $facet->name)) {
            throw new RuntimeException(
                "Facet \"{$facet->name}\" is rendered twice on this page: its inputs and ids "
                .'would be duplicated. Place it on its own before <x-meilifacets::facets>, which shows what is left.'
            );
        }

        $this->record(PlacedControl::Facet, $facet->name);
    }

    /** Designated by name, so the group leaves it alone. */
    public function placeApart(Placeable $facet): void
    {
        $this->apart[$facet->name] = true;

        $this->place($facet);
    }

    public function placeSort(): void
    {
        if ($this->isRendered(PlacedControl::Sort, $this->listing)) {
            throw new RuntimeException(
                "The sort of listing \"{$this->listing}\" is rendered twice on this page: its list and ids "
                .'would be duplicated. Render <x-meilifacets::sort> once per page.'
            );
        }

        $this->record(PlacedControl::Sort, $this->listing);
    }

    /**
     * @param  list<Placeable>  $filters
     * @return list<Placeable>
     */
    public function remaining(array $filters): array
    {
        return array_values(array_filter(
            $filters,
            fn (Placeable $filter): bool => ! isset($this->apart[$filter->name])
        ));
    }

    private function isRendered(PlacedControl $control, string $name): bool
    {
        return isset($this->rendered[$control->value][$name]);
    }

    private function record(PlacedControl $control, string $name): void
    {
        $this->rendered[$control->value][$name] = true;
    }
}
