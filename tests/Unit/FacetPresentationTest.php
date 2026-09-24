<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use InvalidArgumentException;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\SelectionMode;
use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakePresentation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FacetPresentationTest extends TestCase
{
    /** A facet nobody configured must render what it rendered before presentations existed. */
    #[Test]
    public function it_presents_its_values_as_controls_by_default(): void
    {
        $this->assertSame(Presentation::Control, new Facet('product_brand', 'Brand')->presentation);
    }

    #[Test]
    public function it_takes_the_presentation_a_project_declares(): void
    {
        $facet = new Facet('pa_contenance', 'Volume', presentation: Presentation::Pill);

        $this->assertSame(Presentation::Pill, $facet->presentation);
    }

    #[Test]
    public function it_takes_it_as_a_child_terms_facet_too(): void
    {
        $facet = new ChildTermsFacet('product_cat', 'Category', presentation: Presentation::Pill);

        $this->assertSame(Presentation::Pill, $facet->presentation);
    }

    /** R-10: a pill hides a radio, and a radio cannot be unchecked. */
    #[Test]
    public function it_refuses_pills_when_it_holds_one_value_at_a_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Facet "volume" holds one value at a time');

        new Facet('pa_contenance', 'Volume', SelectionMode::Single, name: 'volume', presentation: Presentation::Pill);
    }

    #[Test]
    public function it_keeps_controls_when_it_holds_one_value_at_a_time(): void
    {
        $facet = new Facet('pa_contenance', 'Volume', SelectionMode::Single);

        $this->assertSame(Presentation::Control, $facet->presentedAs(Presentation::Control));
    }

    /** What a template asks for goes through the same guard as what the project declared. */
    #[Test]
    public function it_refuses_pills_a_template_asks_for_on_a_single_selection_facet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Facet('pa_contenance', 'Volume', SelectionMode::Single)->presentedAs(Presentation::Pill);
    }

    #[Test]
    public function it_takes_a_presentation_a_theme_declares(): void
    {
        $facet = new Facet('pa_couleur', 'Colour', presentation: FakePresentation::Tile);

        $this->assertSame('tile', $facet->presentation->slug());
    }

    /** The guard asks the presentation, so a theme's takes a single-selection facet when it says it can. */
    #[Test]
    public function it_lets_a_theme_presentation_that_allows_it_take_one_value_at_a_time(): void
    {
        $facet = new Facet('pa_couleur', 'Colour', SelectionMode::Single, presentation: FakePresentation::Swatch);

        $this->assertSame(FakePresentation::Swatch, $facet->presentation);
    }

    #[Test]
    public function it_refuses_a_theme_presentation_that_does_not_allow_one_value_at_a_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be presented as "tile"');

        new Facet('pa_couleur', 'Colour', SelectionMode::Single, presentation: FakePresentation::Tile);
    }
}
