<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Step 4a of the filter bar: the facet as the trigger of a panel, closed until the client opens it. */
final class CollapsibleFacetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    protected function tearDown(): void
    {
        request()->query->replace([]);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_names_the_facet_with_a_trigger_inside_its_legend(): void
    {
        $toggle = $this->collapsible()->querySelector('legend > '.$this->hooked(Hook::Toggle));

        $this->assertNotNull($toggle, 'The legend holds no trigger.');
        $this->assertSame('button', $toggle->getAttribute('type'));
        $this->assertSame($this->first()->label, $toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent);
    }

    /** Q-4: closed on the server, or every panel flashes open until the client runs. */
    #[Test]
    public function it_renders_the_panel_closed_and_points_the_trigger_at_it(): void
    {
        $document = $this->collapsible();
        $toggle = $document->querySelector($this->hooked(Hook::Toggle));
        $panel = $document->getElementById($toggle->getAttribute('aria-controls'));

        $this->assertSame('false', $toggle->getAttribute('aria-expanded'));
        $this->assertNotNull($panel, 'aria-controls names no element.');
        $this->assertTrue($panel->matches($this->hooked(Hook::Panel)));
        $this->assertTrue($panel->hasAttribute('hidden'));
        $this->assertSame($panel->closest($this->hooked(Hook::Facet)), $toggle->closest($this->hooked(Hook::Facet)));
    }

    #[Test]
    public function it_hides_an_empty_badge_on_a_facet_that_holds_nothing(): void
    {
        $badge = $this->collapsible()->querySelector($this->hooked(Hook::SelectedCount));

        $this->assertTrue($badge->hasAttribute('hidden'));
        $this->assertSame('', $badge->textContent);
    }

    #[Test]
    public function it_counts_on_the_badge_the_values_the_address_holds(): void
    {
        [$one, $two] = array_map(static fn (FacetValue $value): string => $value->slug, $this->listing()->valuesOf($this->first()));
        request()->query->replace([$this->app->make(UrlParameters::class)->for($this->first()->taxonomy) => $one.','.$two]);
        $this->app->forgetScopedInstances();

        $badge = $this->collapsible()->querySelector($this->hooked(Hook::SelectedCount));

        $this->assertFalse($badge->hasAttribute('hidden'));
        $this->assertSame('2', $badge->textContent);
    }

    /** The badge describes the trigger: inside its name it would read « Brand 2 », for the group too. */
    #[Test]
    public function it_describes_the_trigger_with_the_badge_and_keeps_the_badge_out_of_every_name(): void
    {
        $document = $this->collapsible();
        $toggle = $document->querySelector($this->hooked(Hook::Toggle));
        $badge = $document->getElementById($toggle->getAttribute('aria-describedby'));

        $this->assertNotNull($badge, 'aria-describedby names no element.');
        $this->assertTrue($badge->matches($this->hooked(Hook::SelectedCount)));
        $this->assertSame('true', $badge->getAttribute('aria-hidden'));
    }

    /** What the column renders today must not move by a byte when nothing asks to collapse. */
    #[Test]
    public function it_differs_from_the_plain_facet_by_the_trigger_and_the_closed_panel_only(): void
    {
        $plain = Blade::render($this->placing());
        $this->app->forgetScopedInstances();

        $this->assertSame($this->serialized($plain), $this->withoutDisclosure(Blade::render($this->placing('collapsible'))));
        $this->assertStringNotContainsString(Contract::Attribute->value.'="'.Hook::Toggle->value.'"', $plain);
        $this->assertStringNotContainsString(Contract::Attribute->value.'="'.Hook::Panel->value.'"', $plain);
        $this->assertStringNotContainsString(Contract::Attribute->value.'="'.Hook::SelectedCount->value.'"', $plain);
    }

    #[Test]
    public function the_group_collapses_every_filter_it_renders(): void
    {
        $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::facets collapsible />'), LIBXML_NOERROR);

        foreach ($document->querySelectorAll($this->hooked(Hook::Facet)) as $block) {
            $this->assertInstanceOf(Element::class, $block->querySelector($this->hooked(Hook::Toggle)));
            $this->assertFalse($block->hasAttribute('collapsible'));
        }

        $this->assertCount(count($this->listing()->filters()), $document->querySelectorAll($this->hooked(Hook::Toggle)));
    }

    #[Test]
    public function the_group_collapses_nothing_unless_asked(): void
    {
        $this->assertStringNotContainsString(
            Contract::Attribute->value.'="'.Hook::Toggle->value.'"',
            Blade::render('<x-meilifacets::facets />')
        );
    }

    /** A view a theme copies must hold markup only (`R-151`). */
    #[Test]
    public function it_leaves_every_computation_of_the_trigger_to_the_component(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/toggle.blade.php');

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('$ids->', $view);
        $this->assertStringNotContainsString('$listing->', $view);
    }

    private function collapsible(): HTMLDocument
    {
        return HTMLDocument::createFromString(Blade::render($this->placing('collapsible')), LIBXML_NOERROR);
    }

    /** Puts back what the plain facet renders: the label alone in the legend, and an open, unhooked panel. */
    private function withoutDisclosure(string $rendered): string
    {
        $document = HTMLDocument::createFromString($rendered, LIBXML_NOERROR);

        foreach ($document->querySelectorAll('legend') as $legend) {
            $legend->textContent = $legend->querySelector('.meilifacetsFacetToggleLabel')->textContent;
        }

        foreach ($document->querySelectorAll($this->hooked(Hook::Panel)) as $panel) {
            $panel->removeAttribute('hidden');
            $panel->removeAttribute(Contract::Attribute->value);
        }

        return $document->saveHtml();
    }

    private function serialized(string $rendered): string
    {
        return HTMLDocument::createFromString($rendered, LIBXML_NOERROR)->saveHtml();
    }

    private function listing(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->sole();
    }

    private function first(): Facet
    {
        return $this->listing()->facets()[0];
    }

    private function placing(string $attributes = ''): string
    {
        return '<x-meilifacets::facet facet="'.$this->first()->name.'" '.$attributes.' />';
    }

    private function hooked(Hook $hook): string
    {
        return '['.Contract::Attribute->value.'="'.$hook->value.'"]';
    }
}
