<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\View\Components\Drawer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Step 5a of the filter bar: a plain container the client promotes to a modal sheet, and the button that opens it. */
final class DrawerComponentTest extends TestCase
{
    use FindsHooks;
    use HoldsCatalogueValues;
    use SwitchesTheSiteLocale;

    private const string PLACED = '<x-meilifacets::drawer-opener /><x-meilifacets::drawer class="flex-1"><p id="inside">Facets</p>'
        .'<x-slot:footer><x-meilifacets::reset /></x-slot:footer></x-meilifacets::drawer>';

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

    /** Option A: nothing modal on the server, so the markup reads as a plain container without JavaScript. */
    #[Test]
    public function it_renders_a_plain_container_around_what_it_holds(): void
    {
        $drawer = $this->drawer($this->placed());

        $this->assertFalse($drawer->hasAttribute('role'));
        $this->assertFalse($drawer->hasAttribute('aria-modal'));
        $this->assertFalse($drawer->hasAttribute('hidden'));
        $this->assertSame(Drawer::MOBILE, $drawer->getAttribute('data-media'));
        $this->assertSame('meilifacetsDrawer flex-1', $drawer->getAttribute('class'));
        $this->assertNotNull($drawer->querySelector('#inside'));
        $this->assertNotNull($drawer->querySelector($this->hooked(Hook::DrawerSheet).' '.$this->hooked(Hook::DrawerFooter).' '.$this->hooked(Hook::Reset)));
    }

    /** The rule the client refuses to start without: `drawer` holds `drawer-title` and `drawer-close`. */
    #[Test]
    public function it_holds_what_the_contract_requires_of_a_drawer(): void
    {
        $drawer = $this->drawer($this->placed());
        $title = $drawer->querySelector($this->hooked(Hook::DrawerTitle));
        $close = $drawer->querySelector($this->hooked(Hook::DrawerClose));

        $this->assertInstanceOf(Element::class, $title);
        $this->assertSame('h2', strtolower($title->localName));
        $this->assertSame('-1', $title->getAttribute('tabindex'));
        $this->assertNotSame('', $title->getAttribute('id'));
        $this->assertInstanceOf(Element::class, $close);
        $this->assertSame('button', $close->getAttribute('type'));
        $this->assertNotSame('', $close->getAttribute('aria-label'));
    }

    #[Test]
    public function it_points_the_opener_at_the_drawer_it_opens(): void
    {
        $document = $this->placed();
        $opener = $document->querySelector($this->hooked(Hook::DrawerOpen));

        $this->assertSame('button', $opener->getAttribute('type'));
        $this->assertSame('false', $opener->getAttribute('aria-expanded'));
        $this->assertSame($this->drawer($document), $document->getElementById($opener->getAttribute('aria-controls')));
    }

    #[Test]
    public function it_hides_the_count_on_an_opener_while_nothing_is_held(): void
    {
        $count = $this->placed()->querySelector($this->hooked(Hook::DrawerOpen).' '.$this->hooked(Hook::ActiveCount));

        $this->assertTrue($count->hasAttribute('hidden'));
        $this->assertSame('', $count->textContent);
    }

    #[Test]
    public function it_counts_on_the_opener_the_values_the_address_holds(): void
    {
        $this->holdTwoValues();

        $count = $this->placed()->querySelector($this->hooked(Hook::DrawerOpen).' '.$this->hooked(Hook::ActiveCount));

        $this->assertFalse($count->hasAttribute('hidden'));
        $this->assertSame('2', $count->textContent);
    }

    /** Like the badge of a trigger: the count describes the opener, it never enters its name. */
    #[Test]
    public function it_describes_the_opener_with_its_count(): void
    {
        $document = $this->placed();
        $opener = $document->querySelector($this->hooked(Hook::DrawerOpen));
        $count = $document->getElementById($opener->getAttribute('aria-describedby'));

        $this->assertNotNull($count, 'aria-describedby names no element.');
        $this->assertTrue($count->matches($this->hooked(Hook::ActiveCount)));
        $this->assertSame('true', $count->getAttribute('aria-hidden'));
        $this->assertStringNotContainsString('(', $opener->innerHTML);
    }

    /** The icon is decoration: the name stays the word, whatever draws it. */
    #[Test]
    public function it_shows_the_module_icon_before_the_label_when_given_none(): void
    {
        $opener = $this->placed()->querySelector($this->hooked(Hook::DrawerOpen));
        $icon = $opener->firstElementChild;
        $image = $icon->querySelector('img');

        $this->assertSame('true', $icon->getAttribute('aria-hidden'));
        $this->assertStringEndsWith('/modules/meilifacets/images/filters.svg', $image->getAttribute('src'));
        $this->assertSame('', $image->getAttribute('alt'));
        $this->assertSame(['14', '14'], [$image->getAttribute('width'), $image->getAttribute('height')]);
        $this->assertSame(__('Filters'), trim($icon->nextSibling->textContent));
        $this->assertTrue($opener->lastElementChild->matches($this->hooked(Hook::ActiveCount)));
    }

    #[Test]
    public function it_sets_the_icon_a_theme_gives_in_place_of_its_own(): void
    {
        $opener = $this->opener('<x-slot:icon><svg id="sliders" width="16" height="16"></svg></x-slot:icon>');
        $icon = $opener->firstElementChild;

        $this->assertSame('true', $icon->getAttribute('aria-hidden'));
        $this->assertNotNull($icon->querySelector('svg#sliders'));
        $this->assertNull($opener->querySelector('img'));
        $this->assertSame(__('Filters'), trim($icon->nextSibling->textContent));
    }

    /** An empty slot is how a theme asks for no icon at all: nothing is left behind. */
    #[Test]
    public function it_renders_no_icon_when_the_theme_empties_the_slot(): void
    {
        $opener = $this->opener('<x-slot:icon></x-slot:icon>');

        $this->assertSame(1, $opener->childElementCount);
        $this->assertSame(__('Filters'), trim($opener->textContent));
        $this->assertTrue($opener->firstElementChild->matches($this->hooked(Hook::ActiveCount)));
    }

    /** The browser reads the published copy: the source alone is an icon nobody sees. */
    #[Test]
    public function it_points_at_an_icon_that_was_published(): void
    {
        $published = public_path('modules/meilifacets/images/filters.svg');

        $this->assertFileExists($published);
        $this->assertFileEquals(module_path('MeiliFacets', 'resources/assets/images/filters.svg'), $published);
    }

    #[Test]
    public function it_renders_no_footer_unless_given_one(): void
    {
        $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::drawer>Facets</x-meilifacets::drawer>'), LIBXML_NOERROR);

        $this->assertNotNull($document->querySelector($this->hooked(Hook::DrawerSheet)));
        $this->assertNull($document->querySelector($this->hooked(Hook::DrawerFooter)));
    }

    /** Q-3: a theme with another threshold passes it, and restates the stylesheet block. */
    #[Test]
    public function it_takes_another_threshold_and_another_heading_level(): void
    {
        $document = HTMLDocument::createFromString(
            Blade::render('<x-meilifacets::drawer media="(width < 64em)" heading="h3">Facets</x-meilifacets::drawer>'),
            LIBXML_NOERROR
        );

        $this->assertSame('(width < 64em)', $this->drawer($document)->getAttribute('data-media'));
        $this->assertNotNull($document->querySelector('h3'.$this->hooked(Hook::DrawerTitle)));
    }

    #[Test]
    public function it_speaks_the_language_of_the_site(): void
    {
        $document = HTMLDocument::createFromString($this->underLocales('fr', 'fr_FR', static fn (): string => Blade::render(self::PLACED)), LIBXML_NOERROR);

        $this->assertSame('Filtres', trim($document->querySelector($this->hooked(Hook::DrawerTitle))->textContent));
        $this->assertSame('Filtres', trim($document->querySelector($this->hooked(Hook::DrawerOpen))->textContent));
        $this->assertSame('Fermer les filtres', $document->querySelector($this->hooked(Hook::DrawerClose))->getAttribute('aria-label'));
    }

    /** A view a theme copies must hold markup only (`R-151`). */
    #[Test]
    public function it_leaves_every_computation_to_the_components(): void
    {
        foreach (['drawer', 'drawer-opener'] as $name) {
            $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/components/'.$name.'.blade.php');

            $this->assertStringNotContainsString('@php', $view, $name);
            $this->assertStringNotContainsString('$ids->', $view, $name);
            $this->assertStringNotContainsString('$listing->', $view, $name);
        }
    }

    private function opener(string $slot): Element
    {
        return HTMLDocument::createFromString(
            Blade::render('<x-meilifacets::drawer-opener>'.$slot.'</x-meilifacets::drawer-opener>'),
            LIBXML_NOERROR
        )->querySelector($this->hooked(Hook::DrawerOpen));
    }

    private function placed(): HTMLDocument
    {
        return HTMLDocument::createFromString(Blade::render(self::PLACED), LIBXML_NOERROR);
    }

    private function drawer(HTMLDocument $document): Element
    {
        return $document->querySelector($this->hooked(Hook::Drawer));
    }
}
