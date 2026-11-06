<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Closure;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\PlacedControl;

/** Where the templates of one listing placed its filters and its sort, and the row they fill. */
final class PagePlacement
{
    /** @var array<value-of<PlacedControl>, array<string, true>> */
    private array $rendered = [];

    /** @var array<string, true> filter names a template placed on their own */
    private array $placedOnTheirOwn = [];

    private ?FilterRow $row = null;

    /**
     * @param  list<Placeable>  $filters
     * @param  Closure(Placeable): bool  $isInCatalog
     */
    public function __construct(
        private readonly string $listing,
        private readonly array $filters,
        private readonly Closure $isInCatalog,
    ) {}

    /**
     * A declaration comes from the group and stays in it. A component only places its own kind,
     * so a mismatch is caught here rather than rendered wrong.
     *
     * @template T of Placeable
     *
     * @param  class-string<T>  $kind
     * @return T
     */
    public function placeFilter(Placeable $filter, string $kind): Placeable
    {
        $placed = $this->ofKind($filter, $kind);

        if ($this->isRendered(PlacedControl::Facet, $placed->name)) {
            throw PlacementRefused::facetRenderedTwice($placed->name);
        }

        $this->record(PlacedControl::Facet, $placed->name);

        return $placed;
    }

    /**
     * A name comes from a template that placed the filter on its own, so the group leaves it out.
     *
     * @template T of Placeable
     *
     * @param  class-string<T>  $kind
     * @return T
     */
    public function placeNamed(string $name, string $kind): Placeable
    {
        $filter = $this->placeFilter($this->filterNamed($name), $kind);
        $this->placedOnTheirOwn[$filter->name] = true;

        return $filter;
    }

    public function placeSort(): void
    {
        if ($this->isRendered(PlacedControl::Sort, $this->listing)) {
            throw PlacementRefused::sortRenderedTwice($this->listing);
        }

        $this->record(PlacedControl::Sort, $this->listing);
    }

    /**
     * What `<x-meilifacets::listing.facets>` shows: everything a template did not place on its own.
     *
     * @return list<Placeable>
     */
    public function remainingFilters(): array
    {
        return array_values(array_filter(
            $this->filters,
            fn (Placeable $filter): bool => ! isset($this->placedOnTheirOwn[$filter->name]),
        ));
    }

    public function placedFilterCount(): int
    {
        return count($this->placedNames());
    }

    public function startRow(int $limit): void
    {
        $this->row = new FilterRow($this->placedFilterCount(), $limit);
    }

    public function endRow(): void
    {
        $this->row = null;
    }

    public function catalogFilterCountSince(int $mark): int
    {
        return $this->inCatalogAmong(array_slice($this->placedNames(), $mark));
    }

    public function isBeyondRow(Placeable $filter): bool
    {
        if (! $this->row instanceof FilterRow) {
            return false;
        }

        $placed = array_slice($this->placedNames(), $this->row->mark);
        $rank = array_search($filter->name, $placed, true);

        return $rank !== false && $this->inCatalogAmong(array_slice($placed, 0, $rank)) >= $this->row->limit;
    }

    /**
     * @return list<Placeable>
     */
    public function filtersBeyondRow(): array
    {
        return array_values(array_filter($this->filters, $this->isBeyondRow(...)));
    }

    /**
     * @param  list<string>  $names
     */
    private function inCatalogAmong(array $names): int
    {
        return count(array_filter(
            $this->filters,
            fn (Placeable $filter): bool => in_array($filter->name, $names, true) && ($this->isInCatalog)($filter),
        ));
    }

    private function filterNamed(string $name): Placeable
    {
        return array_find($this->filters, static fn (Placeable $filter): bool => $filter->name === $name)
            ?? throw PlacementRefused::unknownFacet(
                $name,
                $this->listing,
                array_map(static fn (Placeable $filter): string => $filter->name, $this->filters),
            );
    }

    /**
     * @template T of Placeable
     *
     * @param  class-string<T>  $kind
     * @return T
     */
    private function ofKind(Placeable $filter, string $kind): Placeable
    {
        if (! $filter instanceof $kind) {
            throw PlacementRefused::otherKind($filter->name, $this->listing, class_basename($filter));
        }

        return $filter;
    }

    /**
     * @return list<string> in the order the page placed them
     */
    private function placedNames(): array
    {
        return array_keys($this->rendered[PlacedControl::Facet->value] ?? []);
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
