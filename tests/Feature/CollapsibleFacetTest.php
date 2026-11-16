<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Modules\MeiliFacets\Tests\Feature\Concerns\HoldsCatalogueValues;
use Tests\TestCase;

trait CollapsibleFacetSupport
{
    private const string NOTHING_MATCHES = 'qqxxzzww-aucun-produit-ne-correspond';

    private function collapsible(): HTMLDocument
    {
        return HTMLDocument::createFromString(Blade::render($this->placing('collapsible')), LIBXML_NOERROR);
    }

    /** @param array<string, string> $query */
    private function collapsibleUnder(array $query): HTMLDocument
    {
        request()->query->replace($query);
        $this->app->forgetScopedInstances();

        return $this->collapsible();
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

    private function placing(string $attributes = ''): string
    {
        return '<x-meilifacets::listing.facet name="products" facet="'.$this->firstFacet()->name.'" '.$attributes.' />';
    }
}

/** Step 4a of the filter bar: the facet as the trigger of a panel, closed until the client opens it. */
uses(TestCase::class, FindsHooks::class, HoldsCatalogueValues::class, CollapsibleFacetSupport::class);

beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->query->replace([]);
    $this->app->forgetScopedInstances();
});

it('names the facet with a trigger inside its legend', function (): void {
    $toggle = $this->collapsible()->querySelector('legend > '.$this->hooked(Hook::Toggle));

    $this->assertNotNull($toggle, 'The legend holds no trigger.');
    expect($toggle->getAttribute('type'))->toBe('button');
    expect($toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent)->toBe($this->firstFacet()->label);
});

/** Q-4: closed on the server, or every panel flashes open until the client runs. */
it('renders the panel closed and points the trigger at it', function (): void {
    $document = $this->collapsible();
    $toggle = $document->querySelector($this->hooked(Hook::Toggle));
    $panel = $document->getElementById($toggle->getAttribute('aria-controls'));

    expect($toggle->getAttribute('aria-expanded'))->toBe('false');
    $this->assertNotNull($panel, 'aria-controls names no element.');
    expect($panel->matches($this->hooked(Hook::Panel)))->toBeTrue();
    expect($panel->hasAttribute('hidden'))->toBeTrue();
    expect($toggle->closest($this->hooked(Hook::Facet)))->toBe($panel->closest($this->hooked(Hook::Facet)));
});

it('hides an empty badge on a facet that holds nothing', function (): void {
    $badge = $this->collapsible()->querySelector($this->hooked(Hook::SelectedCount));

    expect($badge->hasAttribute('hidden'))->toBeTrue();
    expect($badge->textContent)->toBe('');
});

it('counts on the badge the values the address holds', function (): void {
    $this->holdTwoValues();

    $badge = $this->collapsible()->querySelector($this->hooked(Hook::SelectedCount));

    expect($badge->hasAttribute('hidden'))->toBeFalse();
    expect($badge->textContent)->toBe('2');
});

/** R-49: a search that keeps nothing still leaves the pill of a held value, to lift it. */
it('keeps the trigger of a facet holding a value when nothing matches', function (): void {
    [$held] = $this->valuesOfTheFirstFacet(1);

    $document = $this->collapsibleUnder([
        $this->app->make(UrlParameters::class)->reserved(QueryParameter::Query) => self::NOTHING_MATCHES,
        $this->app->make(UrlParameters::class)->for($this->firstFacet()->taxonomy) => $held,
    ]);
    $input = $document->querySelector('input[value="'.$held.'"]');

    expect($document->querySelector($this->hooked(Hook::Facet))->hasAttribute('hidden'))->toBeFalse();
    expect($document->querySelector('legend > '.$this->hooked(Hook::Toggle)))->not->toBeNull();
    expect($document->querySelector($this->hooked(Hook::SelectedCount))->textContent)->toBe('1');
    expect($input->hasAttribute('checked'))->toBeTrue();
    expect($input->closest($this->hooked(Hook::FacetValue))->hasAttribute('hidden'))->toBeFalse();
});

it('hides the trigger of a facet holding nothing when nothing matches', function (): void {
    $document = $this->collapsibleUnder([
        $this->app->make(UrlParameters::class)->reserved(QueryParameter::Query) => self::NOTHING_MATCHES,
    ]);

    expect($document->querySelector($this->hooked(Hook::Facet))->hasAttribute('hidden'))->toBeTrue();
});

/** The badge describes the trigger: inside its name it would read « Brand 2 », for the group too. */
it('describes the trigger with the badge and keeps the badge out of every name', function (): void {
    $document = $this->collapsible();
    $toggle = $document->querySelector($this->hooked(Hook::Toggle));
    $badge = $document->getElementById($toggle->getAttribute('aria-describedby'));

    $this->assertNotNull($badge, 'aria-describedby names no element.');
    expect($badge->matches($this->hooked(Hook::SelectedCount)))->toBeTrue();
    expect($badge->getAttribute('aria-hidden'))->toBe('true');
});

/** What the column renders today must not move by a byte when nothing asks to collapse. */
it('differs from the plain facet by the trigger and the closed panel only', function (): void {
    $plain = Blade::render($this->placing());
    $this->app->forgetScopedInstances();

    expect($this->withoutDisclosure(Blade::render($this->placing('collapsible'))))->toBe($this->serialized($plain));
    $this->assertStringNotContainsString(Contract::Attribute->value.'="'.Hook::Toggle->value.'"', $plain);
    $this->assertStringNotContainsString(Contract::Attribute->value.'="'.Hook::Panel->value.'"', $plain);
    $this->assertStringNotContainsString(Contract::Attribute->value.'="'.Hook::SelectedCount->value.'"', $plain);
});

test('the group collapses every filter it renders', function (): void {
    $document = HTMLDocument::createFromString(Blade::render('<x-meilifacets::listing.facets name="products" collapsible />'), LIBXML_NOERROR);

    foreach ($document->querySelectorAll($this->hooked(Hook::Facet)) as $block) {
        expect($block->querySelector($this->hooked(Hook::Toggle)))->toBeInstanceOf(Element::class);
        expect($block->hasAttribute('collapsible'))->toBeFalse();
    }

    expect($document->querySelectorAll($this->hooked(Hook::Toggle)))->toHaveCount(count($this->catalogue()->filters()));
});

test('the group collapses nothing unless asked', function (): void {
    $this->assertStringNotContainsString(
        Contract::Attribute->value.'="'.Hook::Toggle->value.'"',
        Blade::render('<x-meilifacets::listing.facets name="products" />')
    );
});

/** A view a theme copies must hold markup only (`R-151`). */
it('leaves every computation of the trigger to the component', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/listing/toggle.blade.php');

    $this->assertStringNotContainsString('@php', $view);
    $this->assertStringNotContainsString('$ids->', $view);
    $this->assertStringNotContainsString('$listing->', $view);
});
