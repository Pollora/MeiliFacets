<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use InvalidArgumentException;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakePresentation;

// A facet nobody configured must render what it rendered before presentations existed.
it('presents its values as controls by default', function (): void {
    expect(new Facet('product_brand', 'Brand')->presentation)->toBe(Presentation::Control);
});

it('takes the presentation a project declares', function (): void {
    $facet = new Facet('pa_size', 'Size', presentation: Presentation::Pill);

    expect($facet->presentation)->toBe(Presentation::Pill);
});

it('takes it as a child terms facet too', function (): void {
    $facet = new ChildTermsFacet('product_cat', 'Category', presentation: Presentation::Pill);

    expect($facet->presentation)->toBe(Presentation::Pill);
});

// R-10: a pill hides a radio, and a radio cannot be unchecked.
it('refuses pills when it holds one value at a time', function (): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Facet "size" holds one value at a time');

    new Facet('pa_size', 'Size', SelectionMode::Single, name: 'size', presentation: Presentation::Pill);
});

it('keeps controls when it holds one value at a time', function (): void {
    $facet = new Facet('pa_size', 'Size', SelectionMode::Single);

    expect($facet->acceptedPresentation(Presentation::Control))->toBe(Presentation::Control);
});

// What a template asks for goes through the same guard as what the project declared.
it('refuses pills a template asks for on a single selection facet', function (): void {
    $this->expectException(InvalidArgumentException::class);

    new Facet('pa_size', 'Size', SelectionMode::Single)->acceptedPresentation(Presentation::Pill);
});

it('takes a presentation a theme declares', function (): void {
    $facet = new Facet('pa_couleur', 'Colour', presentation: FakePresentation::Tile);

    expect($facet->presentation->slug())->toBe('tile');
});

// The guard asks the presentation, so a theme's takes a single-selection facet when it says it can.
it('lets a theme presentation that allows it take one value at a time', function (): void {
    $facet = new Facet('pa_couleur', 'Colour', SelectionMode::Single, presentation: FakePresentation::Swatch);

    expect($facet->presentation)->toBe(FakePresentation::Swatch);
});

it('refuses a theme presentation that does not allow one value at a time', function (): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot be presented as "tile"');

    new Facet('pa_couleur', 'Colour', SelectionMode::Single, presentation: FakePresentation::Tile);
});
