<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\PriceFilter as Declaration;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\View\Badge;
use Modules\MeiliFacets\View\Disclosure;
use Modules\MeiliFacets\View\Fill;
use Modules\MeiliFacets\View\RangeHandle;

final class Price extends ListingComponent
{
    public Declaration $facet;

    /** @var list<RangeHandle>|null */
    private ?array $handles = null;

    private ?Range $bounds = null;

    public function __construct(
        CurrentListing $listings,
        public readonly Money $money,
        Declaration|BackedEnum|string $facet,
        string $name = '',
        bool $scroll = false,
        public bool $collapsible = false,
    ) {
        parent::__construct($listings, $name, $scroll);

        $this->facet = $this->listing->placeFacet($this->designated($facet), Declaration::class);
    }

    private function asked(): Range
    {
        return $this->listing->askedPrice();
    }

    public function bounds(): Range
    {
        return $this->bounds ??= $this->facet->boundsFrom($this->listing->facetStats());
    }

    public function panelId(): string
    {
        return $this->ids->facetPanel($this->facet->name);
    }

    public function disclosure(): Disclosure
    {
        return new Disclosure(
            label: $this->facet->label,
            panelId: $this->panelId(),
            badge: new Badge($this->ids->facetSelectedCount($this->facet->name), $this->listing->priceFilterCount()),
        );
    }

    public function showsSlider(): bool
    {
        return $this->facet->shows(PricePart::Slider);
    }

    public function showsFields(): bool
    {
        return $this->facet->shows(PricePart::Fields);
    }

    private function shown(PriceBound $bound, ?float $effective): ?float
    {
        return $this->showsSlider() ? $effective : $bound->of($this->asked());
    }

    /**
     * @return list<RangeHandle>
     */
    public function handles(): array
    {
        return $this->handles ??= $this->build();
    }

    /**
     * @return list<RangeHandle>
     */
    private function build(): array
    {
        $bounds = $this->bounds();
        $low = $this->effective(PriceBound::Min);
        $high = $this->effective(PriceBound::Max);

        return [
            $this->handle(PriceBound::Min, $low, $bounds->min ?? 0.0, $high ?? 0.0),
            $this->handle(PriceBound::Max, $high, $low ?? 0.0, $bounds->max ?? 0.0),
        ];
    }

    private function handle(PriceBound $bound, ?float $effective, float $floor, float $ceiling): RangeHandle
    {
        return new RangeHandle(
            bound: $bound,
            parameter: $this->parameter($bound),
            value: $effective ?? 0.0,
            shown: $this->shown($bound, $effective),
            floor: $floor,
            ceiling: $ceiling,
            at: $this->bounds()->ratio($effective ?? 0.0),
            money: $this->money,
        );
    }

    public function fill(): Fill
    {
        [$min, $max] = $this->handles();

        return new Fill($min->at, $max->at);
    }

    public function readout(): string
    {
        [$min, $max] = $this->handles();

        return $min->written().' – '.$max->written();
    }

    /** A facet can narrow the reachable prices under what was asked. */
    private function effective(PriceBound $bound): ?float
    {
        $asked = $bound->of($this->asked()) ?? $bound->of($this->bounds());

        return $asked === null ? null : $this->bounds()->clamp($asked);
    }

    private function parameter(PriceBound $bound): string
    {
        return $this->listing->parameterForReserved($bound->parameter());
    }

    public function render(): View
    {
        return view('meilifacets::components.price');
    }
}
