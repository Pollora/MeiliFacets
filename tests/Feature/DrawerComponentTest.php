<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\RowOverflow;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Modules\MeiliFacets\Tests\Feature\Concerns\HoldsCatalogueValues;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Modules\MeiliFacets\View\Components\Listing\Drawer;
use Tests\TestCase;

trait DrawerComponentSteps
{
    private int $unfilteredShownCount = 0;

    private const string PLACED = '<x-meilifacets::listing.drawer-opener name="products" /><x-meilifacets::listing.drawer name="products" class="flex-1"><p id="inside">Facets</p>'
        .'<x-slot:footer><x-meilifacets::listing.reset name="products" /></x-slot:footer></x-meilifacets::listing.drawer>';

    /** @var array<string, mixed>|null */
    private ?array $configuredDrawer = null;

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
        $this->unfilteredShownCount = $shown;

        if ($shown === 0) {
            $this->markTestSkipped('The catalogue shows no filter.');
        }

        return $shown;
    }

    private function drawerLimitedTo(int $rowLimit): Element
    {
        return $this->drawerHoldingTheFacets(' :row-limit="'.$rowLimit.'"');
    }

    private function holdAValueThatEmptiesAFacet(): void
    {
        $parameters = $this->app->make(UrlParameters::class);

        foreach ($this->catalogue()->facets() as $facet) {
            foreach ($this->catalogue()->valuesOf($facet) as $value) {
                request()->query->replace([$parameters->for($facet->taxonomy) => $value->slug]);
                $this->app->forgetScopedInstances();

                if (count(array_filter($this->catalogue()->filters(), $this->catalogue()->isShown(...))) < $this->unfilteredShownCount) {
                    return;
                }
            }
        }

        $this->markTestSkipped('No value of the catalogue empties another facet.');
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

// Step 5a of the filter bar: a plain container the client promotes to a modal sheet, and the button that opens it.
uses(TestCase::class, FindsHooks::class, HoldsCatalogueValues::class, SwitchesTheSiteLocale::class, DrawerComponentSteps::class);

beforeEach(function (): void {
    $this->configuredDrawer = config('meilifacets.drawer');
    config(['meilifacets.drawer.row.overflow' => RowOverflow::DEFAULT->value]);

    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->query->replace([]);
    config(['meilifacets.drawer' => $this->configuredDrawer]);
    $this->app->forgetScopedInstances();
});

// Option A: nothing modal on the server, so the markup reads as a plain container without JavaScript.
it('renders a plain container around what it holds', function (): void {
    $drawer = $this->drawer($this->placed());

    expect($drawer->hasAttribute('role'))->toBeFalse();
    expect($drawer->hasAttribute('aria-modal'))->toBeFalse();
    expect($drawer->hasAttribute('hidden'))->toBeFalse();
    expect($drawer->getAttribute('data-media'))->toBe(Drawer::MOBILE);
    expect($drawer->getAttribute('class'))->toBe('meilifacetsDrawer flex-1');
    expect($drawer->querySelector($this->hooked(Hook::DrawerSheet).' '.$this->hooked(Hook::DrawerBody).' #inside'))->not->toBeNull();
    expect($drawer->querySelector($this->hooked(Hook::DrawerSheet).' '.$this->hooked(Hook::DrawerFooter).' '.$this->hooked(Hook::Reset)))->not->toBeNull();
});

// The rule the client refuses to start without: `drawer` holds `drawer-title` and `drawer-close`.
it('holds what the contract requires of a drawer', function (): void {
    $drawer = $this->drawer($this->placed());
    $title = $drawer->querySelector($this->hooked(Hook::DrawerTitle));
    $close = $drawer->querySelector($this->hooked(Hook::DrawerClose));

    expect($title)->toBeInstanceOf(Element::class);
    expect(strtolower($title->localName))->toBe('p', 'the title names the sheet without adding a heading to the page');
    expect($title->getAttribute('tabindex'))->toBe('-1');
    expect($title->getAttribute('id'))->not->toBe('');
    expect($close)->toBeInstanceOf(Element::class);
    expect($close->getAttribute('type'))->toBe('button');
    expect($close->getAttribute('aria-label'))->not->toBe('');
});

it('points the opener at the drawer it opens', function (): void {
    $document = $this->placed();
    $opener = $document->querySelector($this->hooked(Hook::DrawerOpen));

    expect($opener->getAttribute('type'))->toBe('button');
    expect($opener->getAttribute('aria-expanded'))->toBe('false');
    expect($document->getElementById($opener->getAttribute('aria-controls')))->toBe($this->drawer($document));
});

it('hides the count on an opener while nothing is held', function (): void {
    $count = $this->placed()->querySelector($this->hooked(Hook::DrawerOpen).' '.$this->hooked(Hook::ActiveCount));

    expect($count->hasAttribute('hidden'))->toBeTrue();
    expect($count->textContent)->toBe('');
});

it('counts on the opener the values the address holds', function (): void {
    $this->holdTwoValues();

    $count = $this->placed()->querySelector($this->hooked(Hook::DrawerOpen).' '.$this->hooked(Hook::ActiveCount));

    expect($count->hasAttribute('hidden'))->toBeFalse();
    expect($count->textContent)->toBe('2');
});

// Like the badge of a trigger: the count describes the opener, it never enters its name.
it('describes the opener with its count', function (): void {
    $document = $this->placed();
    $opener = $document->querySelector($this->hooked(Hook::DrawerOpen));
    $count = $document->getElementById($opener->getAttribute('aria-describedby'));

    $this->assertNotNull($count, 'aria-describedby names no element.');
    expect($count->matches($this->hooked(Hook::ActiveCount)))->toBeTrue();
    expect($count->getAttribute('aria-hidden'))->toBe('true');
    expect($opener->innerHTML)->not->toContain('(');
});

// The icon is decoration: the name stays the word, whatever draws it.
it('shows the module icon before the label when given none', function (): void {
    $opener = $this->placed()->querySelector($this->hooked(Hook::DrawerOpen));
    $icon = $opener->firstElementChild;
    $image = $icon->querySelector('img');

    expect($icon->getAttribute('aria-hidden'))->toBe('true');
    expect($image->getAttribute('src'))->toEndWith('/modules/meilifacets/images/filters.svg');
    expect($image->getAttribute('alt'))->toBe('');
    expect([$image->getAttribute('width'), $image->getAttribute('height')])->toBe(['14', '14']);
    expect(trim($icon->nextSibling->textContent))->toBe(__('Filters'));
    expect($opener->lastElementChild->matches($this->hooked(Hook::ActiveCount)))->toBeTrue();
});

it('sets the icon a theme gives in place of its own', function (): void {
    $opener = $this->opener('<x-slot:icon><svg id="sliders" width="16" height="16"></svg></x-slot:icon>');
    $icon = $opener->firstElementChild;

    expect($icon->getAttribute('aria-hidden'))->toBe('true');
    expect($icon->querySelector('svg#sliders'))->not->toBeNull();
    expect($opener->querySelector('img'))->toBeNull();
    expect(trim($icon->nextSibling->textContent))->toBe(__('Filters'));
});

// An empty slot is how a theme asks for no icon at all: nothing is left behind.
it('renders no icon when the theme empties the slot', function (): void {
    $opener = $this->opener('<x-slot:icon></x-slot:icon>');

    expect($opener->childElementCount)->toBe(1);
    expect(trim($opener->textContent))->toBe(__('Filters'));
    expect($opener->firstElementChild->matches($this->hooked(Hook::ActiveCount)))->toBeTrue();
});

// The browser reads the published copy: the source alone is an icon nobody sees.
it('points at an icon that was published', function (): void {
    $published = public_path('modules/meilifacets/images/filters.svg');

    $this->assertFileExists($published);
    $this->assertFileEquals(module_path('MeiliFacets', 'resources/assets/images/filters.svg'), $published);
});

it('renders no footer unless given one', function (): void {
    $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::listing.drawer name="products">Facets</x-meilifacets::listing.drawer>'), LIBXML_NOERROR);

    expect($document->querySelector($this->hooked(Hook::DrawerSheet)))->not->toBeNull();
    expect($document->querySelector($this->hooked(Hook::DrawerFooter)))->toBeNull();
});

// Q-3: a theme with another threshold passes it, and restates the stylesheet block.
it('takes another threshold', function (): void {
    $document = HTMLDocument::createFromString(
        Blade::render('<x-meilifacets::listing.drawer name="products" media="(width < 64em)">Facets</x-meilifacets::listing.drawer>'),
        LIBXML_NOERROR
    );

    expect($this->drawer($document)->getAttribute('data-media'))->toBe('(width < 64em)');
});

it('marks side sheet overflow past its row limit', function (): void {
    $shown = $this->shownFilterCount();

    expect($this->drawerLimitedTo($shown - 1)->getAttribute('data-overflow'))->toBe(RowOverflow::SideSheet->value);
    expect($this->drawerLimitedTo($shown - 1)->hasAttribute('data-side-sheet'))->toBeFalse();
    expect($this->drawerLimitedTo($shown)->hasAttribute('data-overflow'))->toBeFalse();
});

it('takes its row limit from the configuration unless given one', function (): void {
    $shown = $this->shownFilterCount();

    config(['meilifacets.drawer.row.limit' => $shown]);
    expect($this->drawerHoldingTheFacets()->hasAttribute('data-overflow'))->toBeFalse();

    config(['meilifacets.drawer.row.limit' => $shown - 1]);
    expect($this->drawerHoldingTheFacets()->hasAttribute('data-overflow'))->toBeTrue();
    expect($this->drawerLimitedTo($shown)->hasAttribute('data-overflow'))->toBeFalse();
});

it('takes its row overflow from the configuration unless given one', function (): void {
    $limited = ' :row-limit="'.($this->shownFilterCount() - 1).'"';

    config(['meilifacets.drawer.row.overflow' => RowOverflow::ShowMore->value]);
    expect($this->drawerHoldingTheFacets($limited)->getAttribute('data-overflow'))->toBe(RowOverflow::ShowMore->value);

    $sideSheet = $this->drawerHoldingTheFacets($limited.' row-overflow="'.RowOverflow::SideSheet->value.'"');
    expect($sideSheet->getAttribute('data-overflow'))->toBe(RowOverflow::SideSheet->value);
});

it('accepts its row overflow spelled with a hyphen', function (): void {
    $drawer = $this->drawerHoldingTheFacets(' :row-limit="'.($this->shownFilterCount() - 1).'" row-overflow="show-more"');

    expect($drawer->getAttribute('data-overflow'))->toBe(RowOverflow::ShowMore->value);
    expect($drawer->querySelector($this->hooked(Hook::DrawerShowMore)))->not->toBeNull();
});

it('keeps its first shown filters in the row and marks the others with show more', function (): void {
    $limit = $this->shownFilterCount() - 1;
    $drawer = $this->drawerShowingMore($limit);
    $shown = $drawer->querySelectorAll($this->hooked(Hook::Facet).':not([hidden])');
    $kept = array_slice(iterator_to_array($shown), 0, $limit);

    expect($drawer->getAttribute('data-overflow'))->toBe(RowOverflow::ShowMore->value);
    expect(array_filter($kept, static fn (Element $filter): bool => $filter->hasAttribute('data-beyond-row')))->toBe([]);
    expect($shown[$limit]->hasAttribute('data-beyond-row'))->toBeTrue();
    expect($drawer->querySelectorAll($this->hooked(Hook::Facet).'[data-beyond-row] ~ '.$this->hooked(Hook::Facet).':not([data-beyond-row])')->length)->toBe(0);
});

// A filter emptying facets must not lift the row: clearing it in the browser would leave every facet on show.
it('keeps the row of the whole catalogue under a filter that empties facets', function (): void {
    $limit = $this->shownFilterCount() - 1;
    $this->holdAValueThatEmptiesAFacet();

    expect($this->drawerShowingMore($limit)->getAttribute('data-overflow'))->toBe(RowOverflow::ShowMore->value);
});

it('hides show more while every filter past the row is empty', function (): void {
    $limit = $this->shownFilterCount() - 1;
    $this->holdAValueThatEmptiesAFacet();
    $drawer = $this->drawerShowingMore($limit);
    $pastTheRow = $drawer->querySelectorAll($this->hooked(Hook::Facet).'[data-beyond-row]:not([hidden])')->length;

    expect($drawer->querySelector($this->hooked(Hook::DrawerShowMore))->hasAttribute('hidden'))->toBe($pastTheRow === 0);
});

it('ends its row with a show more button that opens it', function (): void {
    $drawer = $this->drawerShowingMore($this->shownFilterCount() - 1);
    $button = $drawer->querySelector($this->hooked(Hook::DrawerShowMore));

    expect($button)->toBeInstanceOf(Element::class);
    expect($button)->toBe($drawer->querySelector($this->hooked(Hook::DrawerBody))->lastElementChild);
    expect($button->getAttribute('type'))->toBe('button');
    expect($button->getAttribute('aria-controls'))->toBe($drawer->getAttribute('id'));
    expect($button->getAttribute('aria-expanded'))->toBe('false');
    expect(trim($button->textContent))->toBe(__('Show more'));
    expect($button->getAttribute('aria-label'))->toBe(__('Show more filters'));
    expect($button->querySelector('[aria-hidden]'))->toBeNull();
});

it('sets the icon a theme gives before show more', function (): void {
    $drawer = $this->drawerHoldingTheFacets(
        ' :row-limit="'.($this->shownFilterCount() - 1).'" row-overflow="show-more"',
        '<x-slot:show-more><svg id="sliders"></svg></x-slot:show-more>',
    );
    $icon = $drawer->querySelector($this->hooked(Hook::DrawerShowMore))->firstElementChild;

    expect($icon->getAttribute('aria-hidden'))->toBe('true');
    expect($icon->querySelector('svg#sliders'))->not->toBeNull();
});

it('marks nothing and shows no button within its row limit', function (): void {
    $drawer = $this->drawerShowingMore($this->shownFilterCount());

    expect($drawer->hasAttribute('data-overflow'))->toBeFalse();
    expect($drawer->querySelector($this->hooked(Hook::DrawerShowMore)))->toBeNull();
    expect($drawer->querySelector('[data-beyond-row]'))->toBeNull();
});

it('marks no filter and shows no button as a side sheet', function (): void {
    $drawer = $this->drawerLimitedTo($this->shownFilterCount() - 1);

    expect($drawer->querySelector($this->hooked(Hook::DrawerShowMore)))->toBeNull();
    expect($drawer->querySelector('[data-beyond-row]'))->toBeNull();
});

it('marks no filter placed after it', function (): void {
    $this->app->forgetScopedInstances();
    $document = HTMLDocument::createFromString(
        Blade::render('<x-meilifacets::listing.drawer name="products" :row-limit="0" row-overflow="show-more">Facets</x-meilifacets::listing.drawer>'
            .'<x-meilifacets::listing.facets name="products" />'),
        LIBXML_NOERROR
    );

    expect($this->shownFilterCount())->toBeGreaterThan(0);
    expect($document->querySelector('[data-beyond-row]'))->toBeNull();
});

it('counts no filter placed outside it', function (): void {
    $empty = HTMLDocument::createFromString(
        Blade::render('<x-meilifacets::listing.facets name="products" /><x-meilifacets::listing.drawer name="products" :row-limit="0">Facets</x-meilifacets::listing.drawer>'),
        LIBXML_NOERROR
    );

    expect($this->shownFilterCount())->toBeGreaterThan(0);
    expect($this->drawer($empty)->hasAttribute('data-overflow'))->toBeFalse();
});

it('speaks the language of the site', function (): void {
    $document = HTMLDocument::createFromString($this->underLocales('fr', 'fr_FR', static fn (): string => Blade::render(self::PLACED)), LIBXML_NOERROR);

    expect(trim($document->querySelector($this->hooked(Hook::DrawerTitle))->textContent))->toBe('Filtres');
    expect(trim($document->querySelector($this->hooked(Hook::DrawerOpen))->textContent))->toBe('Filtres');
    expect($document->querySelector($this->hooked(Hook::DrawerClose))->getAttribute('aria-label'))->toBe('Fermer les filtres');
});

// A view a theme copies must hold markup only (`R-151`).
it('leaves every computation to the components', function (): void {
    foreach (['drawer', 'drawer-opener'] as $name) {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/components/listing/'.$name.'.blade.php');

        $this->assertStringNotContainsString('@php', $view, $name);
        $this->assertStringNotContainsString('$ids->', $view, $name);
        $this->assertStringNotContainsString('$listing->', $view, $name);
    }
});
