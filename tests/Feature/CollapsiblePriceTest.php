<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\ParentNode;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\PriceFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Step 4c of the filter bar: the price behind the same trigger as a facet, closed until the client opens it. */
final class CollapsiblePriceTest extends TestCase
{
    use FindsHooks;

    private const string LABEL = 'Price';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    protected function tearDown(): void
    {
        request()->replace([]);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    /** With a slider the plain legend is visually hidden (`namesItself()`); a trigger has to be seen. */
    #[Test]
    public function it_names_the_price_with_a_visible_trigger_inside_its_legend(): void
    {
        $legend = $this->render('collapsible')->querySelector('legend');
        $toggle = $legend->querySelector($this->hooked(Hook::Toggle));

        $this->assertNotNull($toggle, 'The legend holds no trigger.');
        $this->assertSame('meilifacetsFacetLabel', $legend->getAttribute('class'));
        $this->assertSame(self::LABEL, $toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent);
    }

    /** Q-4: closed on the server, or the panel flashes open until the client runs. */
    #[Test]
    public function it_renders_the_panel_closed_and_points_the_trigger_at_it(): void
    {
        $document = $this->render('collapsible');
        $toggle = $this->one($document, Hook::Toggle);
        $panel = $document->getElementById($toggle->getAttribute('aria-controls'));

        $this->assertSame('false', $toggle->getAttribute('aria-expanded'));
        $this->assertNotNull($panel, 'aria-controls names no element.');
        $this->assertTrue($panel->matches($this->hooked(Hook::Panel)));
        $this->assertTrue($panel->hasAttribute('hidden'));
        $this->assertNotNull($panel->querySelector($this->hooked(Hook::PriceTrack)), 'The track is not in the panel.');
    }

    #[Test]
    public function it_hides_an_empty_badge_while_no_range_is_held(): void
    {
        $badge = $this->one($this->render('collapsible'), Hook::SelectedCount);

        $this->assertTrue($badge->hasAttribute('hidden'));
        $this->assertSame('', $badge->textContent);
    }

    /** `R-123`: a range is one filter, whether it holds one end or two. */
    #[Test]
    public function it_counts_a_held_range_once_on_the_badge(): void
    {
        [$low, $high] = $this->catalogueBounds();
        $inside = ['min_price' => (string) ($low + 1), 'max_price' => (string) ($high - 1)];

        foreach ([$inside, ['max_price' => $inside['max_price']]] as $query) {
            $badge = $this->one($this->render('collapsible', $query), Hook::SelectedCount);

            $this->assertFalse($badge->hasAttribute('hidden'), 'No badge for '.http_build_query($query).'.');
            $this->assertSame('1', $badge->textContent);
        }
    }

    /** Measured once against the view before step 4c, byte for byte; what can move is held here. */
    #[Test]
    public function it_differs_from_the_plain_price_by_the_legend_and_the_closed_panel_only(): void
    {
        $plain = $this->render();
        $collapsible = $this->render('collapsible');

        $this->assertSame(self::LABEL, trim($plain->querySelector('legend')->textContent));
        $this->assertNull($this->one($plain, Hook::Toggle));
        $this->assertNull($this->one($plain, Hook::Panel));
        $this->assertNull($this->one($plain, Hook::SelectedCount));
        $this->assertSame($this->withoutLegend($plain), $this->withoutDisclosure($collapsible));
    }

    /** A view a theme copies must hold markup only (`R-151`). */
    #[Test]
    public function it_leaves_every_computation_of_the_trigger_to_the_component(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/price.blade.php');

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('$ids->', $view);
        $this->assertStringNotContainsString('$listing->', $view);
    }

    private function withoutDisclosure(HTMLDocument $document): string
    {
        foreach ($document->querySelectorAll($this->hooked(Hook::Panel)) as $panel) {
            $panel->removeAttribute('hidden');
            $panel->removeAttribute(Contract::Attribute->value);
        }

        return $this->withoutLegend($document);
    }

    private function withoutLegend(HTMLDocument $document): string
    {
        $document->querySelector('legend')->remove();

        return $document->saveHtml();
    }

    /**
     * Read off the catalogue, which changes with every import: fixed amounts would fall outside it.
     *
     * @return array{float, float}
     */
    private function catalogueBounds(): array
    {
        $handle = $this->one($this->render(), Hook::PriceHandle);

        $this->assertNotNull($handle, 'The catalogue has no price to draw a track from.');

        return [(float) $handle->getAttribute('aria-valuemin'), (float) $handle->getAttribute('aria-valuemax')];
    }

    /**
     * @param  array<string, string>  $query
     */
    private function render(string $attributes = '', array $query = []): HTMLDocument
    {
        request()->replace($query);
        $this->app->forgetScopedInstances();

        $markup = Blade::render(
            '<x-meilifacets::price :facet="$filter" '.$attributes.' />',
            ['filter' => new PriceFilter(self::LABEL, [PricePart::Slider, PricePart::Fields], 'price-under-test')]
        );

        return HTMLDocument::createFromString('<div>'.$markup.'</div>', LIBXML_NOERROR);
    }

    private function one(ParentNode $within, Hook $hook): ?Element
    {
        return $within->querySelector($this->hooked($hook));
    }
}
