<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Contracts\ValuePresentation;
use Modules\MeiliFacets\Enums\InputType;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet as Declaration;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\View\Badge;
use Modules\MeiliFacets\View\CountLabel;
use Modules\MeiliFacets\View\Disclosure;

final class Facet extends ListingComponent
{
    public Declaration $facet;

    /** @var list<FacetValue> */
    public array $values;

    public ValuePresentation $presentation;

    private ?string $countPattern = null;

    public function __construct(
        CurrentListing $listings,
        private readonly CountLabel $countLabel,
        Declaration|BackedEnum|string $facet,
        string $name = '',
        bool $scroll = false,
        ValuePresentation|string|null $presentation = null,
        public bool $collapsible = false,
    ) {
        parent::__construct($listings, $name, $scroll);

        $this->facet = $this->listing->placeFacet($this->designated($facet), Declaration::class);
        $this->presentation = $this->presentationOf($presentation);

        $this->values = $this->listing->valuesOf($this->facet);
    }

    public function marksPresentation(): bool
    {
        return $this->presentation !== Presentation::Control;
    }

    public function inputType(): string
    {
        return InputType::forSelection($this->facet->selection)->value;
    }

    public function inputName(): string
    {
        return $this->listing->parameterFor($this->facet->taxonomy);
    }

    public function panelId(): string
    {
        return $this->ids->facetPanel($this->facet->name);
    }

    public function disclosure(): Disclosure
    {
        $selected = $this->listing->state()->selected($this->facet->taxonomy);

        return new Disclosure(
            label: $this->facet->label,
            panelId: $this->panelId(),
            badge: new Badge($this->ids->facetSelectedCount($this->facet->name), count($selected)),
        );
    }

    public function labelId(FacetValue $value): string
    {
        return $this->ids->facetValueLabel($this->facet->name, $value->slug);
    }

    public function countId(FacetValue $value): string
    {
        return $this->ids->facetCount($this->facet->name, $value->slug);
    }

    public function countLabel(FacetValue $value): string
    {
        $this->countPattern ??= __(':count result|:count results');

        return $this->countLabel->of($this->countPattern, $value->count);
    }

    public function hasFoldedValues(): bool
    {
        return array_any($this->values, static fn (FacetValue $value): bool => $value->folded && $value->count > 0);
    }

    public function hasReadableValues(): bool
    {
        return array_any($this->values, static fn (FacetValue $value): bool => ! $value->folded);
    }

    /** A plain attribute can only name the module's presentations; a theme's arrives bound (`:presentation`). */
    private function presentationOf(ValuePresentation|string|null $override): ValuePresentation
    {
        return match (true) {
            $override === null => $this->facet->presentation,
            is_string($override) => $this->facet->presentedAs(Presentation::from($override)),
            default => $this->facet->presentedAs($override),
        };
    }

    public function render(): View
    {
        return view('meilifacets::components.facet');
    }
}
