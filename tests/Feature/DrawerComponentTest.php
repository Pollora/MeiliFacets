<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\RowOverflow;
use Modules\MeiliFacets\View\Components\Listing\Drawer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Step 5a of the filter bar: a plain container the client promotes to a modal sheet, and the button that opens it. */
final class DrawerComponentTest extends TestCase
{
    use FindsHooks;
    use HoldsCatalogueValues;
    use SwitchesTheSiteLocale;

    private const string PLACED = '<x-meilifacets::listing.drawer-opener name="products" /><x-meilifacets::listing.drawer name="products" class="flex-1"><p id="inside">Facets</p>'
        .'<x-slot:footer><x-meilifacets::listing.reset name="products" /></x-slot:footer></x-meilifacets::listing.drawer>';

    /** @var array<string, mixed>|null */
    private ?array $configuredDrawer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuredDrawer = config('meilifacets.drawer');
        config(['meilifacets.drawer.row.overflow' => RowOverflow::DEFAULT->value]);

        $this->app->forgetScopedInstances();
    }

    protected function tearDown(): void
    {
        request()->query->replace([]);
        config(['meilifacets.drawer' => $this->configuredDrawer]);
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
        $this->assertNotNull($drawer->querySelector($this->hooked(Hook::DrawerSheet).' '.$this->hooked(Hook::DrawerBody).' #inside'));
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
        $this->assertSame('p', strtolower($title->localName), 'the title names the sheet without adding a heading to the page');
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
        $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::listing.drawer name="products">Facets</x-meilifacets::listing.drawer>'), LIBXML_NOERROR);

        $this->assertNotNull($document->querySelector($this->hooked(Hook::DrawerSheet)));
        $this->assertNull($document->querySelector($this->hooked(Hook::DrawerFooter)));
    }

    /** Q-3: a theme with another threshold passes it, and restates the stylesheet block. */
    #[Test]
    public function it_takes_another_threshold(): void
    {
        $document = HTMLDocument::createFromString(
            Blade::render('<x-meilifacets::listing.drawer name="products" media="(width < 64em)">Facets</x-meilifacets::listing.drawer>'),
            LIBXML_NOERROR
        );

        $this->assertSame('(width < 64em)', $this->drawer($document)->getAttribute('data-media'));
    }

    #[Test]
    public function it_marks_side_sheet_overflow_past_its_row_limit(): void
    {
        $shown = $this->shownFilterCount();

        $this->assertSame(RowOverflow::SideSheet->value, $this->drawerLimitedTo($shown - 1)->getAttribute('data-overflow'));
        $this->assertFalse($this->drawerLimitedTo($shown - 1)->hasAttribute('data-side-sheet'));
        $this->assertFalse($this->drawerLimitedTo($shown)->hasAttribute('data-overflow'));
    }

    #[Test]
    public function it_takes_its_row_limit_from_the_configuration_unless_given_one(): void
    {
        $shown = $this->shownFilterCount();

        config(['meilifacets.drawer.row.limit' => $shown]);
        $this->assertFalse($this->drawerHoldingTheFacets()->hasAttribute('data-overflow'));

        config(['meilifacets.drawer.row.limit' => $shown - 1]);
        $this->assertTrue($this->drawerHoldingTheFacets()->hasAttribute('data-overflow'));
        $this->assertFalse($this->drawerLimitedTo($shown)->hasAttribute('data-overflow'));
    }

    #[Test]
    public function it_takes_its_row_overflow_from_the_configuration_unless_given_one(): void
    {
        $limited = ' :row-limit="'.($this->shownFilterCount() - 1).'"';

        config(['meilifacets.drawer.row.overflow' => RowOverflow::ShowMore->value]);
        $this->assertSame(RowOverflow::ShowMore->value, $this->drawerHoldingTheFacets($limited)->getAttribute('data-overflow'));

        $sideSheet = $this->drawerHoldingTheFacets($limited.' row-overflow="'.RowOverflow::SideSheet->value.'"');
        $this->assertSame(RowOverflow::SideSheet->value, $sideSheet->getAttribute('data-overflow'));
    }

    #[Test]
    public function it_accepts_its_row_overflow_spelled_with_a_hyphen(): void
    {
        $drawer = $this->drawerHoldingTheFacets(' :row-limit="'.($this->shownFilterCount() - 1).'" row-overflow="show-more"');

        $this->assertSame(RowOverflow::ShowMore->value, $drawer->getAttribute('data-overflow'));
        $this->assertNotNull($drawer->querySelector($this->hooked(Hook::DrawerShowMore)));
    }

    #[Test]
    public function it_keeps_its_first_shown_filters_in_the_row_and_marks_the_others_with_show_more(): void
    {
        $limit = $this->shownFilterCount() - 1;
        $drawer = $this->drawerShowingMore($limit);
        $shown = $drawer->querySelectorAll($this->hooked(Hook::Facet).':not([hidden])');
        $kept = array_slice(iterator_to_array($shown), 0, $limit);

        $this->assertSame(RowOverflow::ShowMore->value, $drawer->getAttribute('data-overflow'));
        $this->assertSame([], array_filter($kept, static fn (Element $filter): bool => $filter->hasAttribute('data-beyond-row')));
        $this->assertTrue($shown[$limit]->hasAttribute('data-beyond-row'));
        $this->assertSame(0, $drawer->querySelectorAll($this->hooked(Hook::Facet).'[data-beyond-row] ~ '.$this->hooked(Hook::Facet).':not([data-beyond-row])')->length);
    }

    #[Test]
    public function it_ends_its_row_with_a_show_more_button_that_opens_it(): void
    {
        $drawer = $this->drawerShowingMore($this->shownFilterCount() - 1);
        $button = $drawer->querySelector($this->hooked(Hook::DrawerShowMore));

        $this->assertInstanceOf(Element::class, $button);
        $this->assertSame($drawer->querySelector($this->hooked(Hook::DrawerBody))->lastElementChild, $button);
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame($drawer->getAttribute('id'), $button->getAttribute('aria-controls'));
        $this->assertSame('false', $button->getAttribute('aria-expanded'));
        $this->assertSame(__('Show more'), trim($button->textContent));
        $this->assertSame(__('Show more filters'), $button->getAttribute('aria-label'));
        $this->assertNull($button->querySelector('[aria-hidden]'));
    }

    #[Test]
    public function it_sets_the_icon_a_theme_gives_before_show_more(): void
    {
        $drawer = $this->drawerHoldingTheFacets(
            ' :row-limit="'.($this->shownFilterCount() - 1).'" row-overflow="show-more"',
            '<x-slot:show-more><svg id="sliders"></svg></x-slot:show-more>',
        );
        $icon = $drawer->querySelector($this->hooked(Hook::DrawerShowMore))->firstElementChild;

        $this->assertSame('true', $icon->getAttribute('aria-hidden'));
        $this->assertNotNull($icon->querySelector('svg#sliders'));
    }

    #[Test]
    public function it_marks_nothing_and_shows_no_button_within_its_row_limit(): void
    {
        $drawer = $this->drawerShowingMore($this->shownFilterCount());

        $this->assertFalse($drawer->hasAttribute('data-overflow'));
        $this->assertNull($drawer->querySelector($this->hooked(Hook::DrawerShowMore)));
        $this->assertNull($drawer->querySelector('[data-beyond-row]'));
    }

    #[Test]
    public function it_marks_no_filter_and_shows_no_button_as_a_side_sheet(): void
    {
        $drawer = $this->drawerLimitedTo($this->shownFilterCount() - 1);

        $this->assertNull($drawer->querySelector($this->hooked(Hook::DrawerShowMore)));
        $this->assertNull($drawer->querySelector('[data-beyond-row]'));
    }

    #[Test]
    public function it_marks_no_filter_placed_after_it(): void
    {
        $this->app->forgetScopedInstances();
        $document = HTMLDocument::createFromString(
            Blade::render('<x-meilifacets::listing.drawer name="products" :row-limit="0" row-overflow="show-more">Facets</x-meilifacets::listing.drawer>'
                .'<x-meilifacets::listing.facets name="products" />'),
            LIBXML_NOERROR
        );

        $this->assertGreaterThan(0, $this->shownFilterCount());
        $this->assertNull($document->querySelector('[data-beyond-row]'));
    }

    #[Test]
    public function it_counts_no_filter_placed_outside_it(): void
    {
        $empty = HTMLDocument::createFromString(
            Blade::render('<x-meilifacets::listing.facets name="products" /><x-meilifacets::listing.drawer name="products" :row-limit="0">Facets</x-meilifacets::listing.drawer>'),
            LIBXML_NOERROR
        );

        $this->assertGreaterThan(0, $this->shownFilterCount());
        $this->assertFalse($this->drawer($empty)->hasAttribute('data-overflow'));
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
            $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/components/listing/'.$name.'.blade.php');

            $this->assertStringNotContainsString('@php', $view, $name);
            $this->assertStringNotContainsString('$ids->', $view, $name);
            $this->assertStringNotContainsString('$listing->', $view, $name);
        }
    }

    private function opener(string $slot): Element
    {
        return HTMLDocument::createFromString(
            Blade::render('<x-meilifacets::listing.drawer-opener name="products">'.$slot.'</x-meilifacets::listing.drawer-opener>'),
            LIBXML_NOERROR
        )->querySelector($this->hooked(Hook::DrawerOpen));
    }

    private function placed(): HTMLDocument
    {
        return HTMLDocument::createFromString(Blade::render(self::PLACED), LIBXML_NOERROR);
    }

    private function shownFilterCount(): int
    {
        $listing = $this->catalogue();
        $shown = count(array_filter($listing->filters(), $listing->isShown(...)));

        if ($shown === 0) {
            $this->markTestSkipped('The catalogue shows no filter.');
        }

        return $shown;
    }

    private function drawerLimitedTo(int $rowLimit): Element
    {
        return $this->drawerHoldingTheFacets(' :row-limit="'.$rowLimit.'"');
    }

    private function drawerShowingMore(int $rowLimit): Element
    {
        return $this->drawerHoldingTheFacets(' :row-limit="'.$rowLimit.'" row-overflow="'.RowOverflow::ShowMore->value.'"');
    }

    private function drawerHoldingTheFacets(string $attributes = '', string $slots = ''): Element
    {
        $this->app->forgetScopedInstances();

        return $this->drawer(HTMLDocument::createFromString(
            Blade::render('<x-meilifacets::listing.drawer name="products"'.$attributes.'><x-meilifacets::listing.facets name="products" />'.$slots.'</x-meilifacets::listing.drawer>'),
            LIBXML_NOERROR
        ));
    }

    private function drawer(HTMLDocument $document): Element
    {
        return $document->querySelector($this->hooked(Hook::Drawer));
    }
}
