<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\InputType;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet as Declaration;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\View\CountLabel;

final class Facet extends ListingComponent
{
    public Declaration $facet;

    /** @var list<FacetValue> */
    public array $values;

    private ?string $countPattern = null;

    public function __construct(
        CurrentListing $listings,
        private readonly CountLabel $countLabel,
        Declaration|BackedEnum|string $facet,
        string $name = '',
        bool $scroll = false,
    ) {
        parent::__construct($listings, $name, $scroll);

        $this->facet = $this->listing->placeFacet($this->designated($facet), Declaration::class);

        $this->values = $this->listing->valuesOf($this->facet);
    }

    public function inputType(): string
    {
        return InputType::forSelection($this->facet->selection)->value;
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

    public function render(): View
    {
        return view('meilifacets::components.facet');
    }
}
