<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\ParentNode;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Tests\TestCase;

trait PriceComponentSteps
{
    /** @var array{concrete: \Closure, shared: bool} */
    private array $engine;

    /**
     * The filter is declared here, not read off the host project: adding one to the
     * shop must not decide what this suite asserts.
     *
     * @param  list<PricePart>  $parts
     * @param  array<string, string>  $query
     */
    private function render(array $parts, array $query = []): HTMLDocument
    {
        // Replace, never merge: merging leaves one test reading the parameters of the last.
        request()->replace($query);
        $this->app->forgetScopedInstances();

        $markup = Blade::render(
            '<x-meilifacets::listing.price name="products" :facet="$filter" />',
            ['filter' => new PriceFilter('Price', $parts, 'price-under-test')]
        );

        return HTMLDocument::createFromString('<div>'.$markup.'</div>', LIBXML_NOERROR);
    }

    /**
     * Read off the catalogue, which changes with every import: fixed amounts would fall outside it.
     *
     * @return array{float, float}
     */
    private function catalogueBounds(): array
    {
        $handle = $this->one($this->render([PricePart::Slider]), Hook::PriceHandle);

        $this->assertNotNull($handle, 'The catalogue has no price to draw a track from.');

        return [(float) $handle->getAttribute('aria-valuemin'), (float) $handle->getAttribute('aria-valuemax')];
    }

    private function one(ParentNode $within, Hook $hook): ?Element
    {
        return $within->querySelector('['.Contract::Attribute->value.'="'.$hook->value.'"]');
    }

    private function aFacetName(): string
    {
        return $this->app->make(CurrentListing::class)->named(ProductListing::NAME)->facets()[0]->name;
    }
}

// The parts a project declared decide what is drawn. What always exists is the pair
// of inputs: they carry the parameter names and the current bounds the client reads.
uses(TestCase::class, PriceComponentSteps::class);

beforeEach(function (): void {
    $this->engine = $this->app->getBindings()[SearchEngine::class];
    $this->app->forgetScopedInstances();
});

// The suite shares one application: a fake engine left bound would answer a later class.
afterEach(function (): void {
    $this->app->bind(SearchEngine::class, $this->engine['concrete'], $this->engine['shared']);
    $this->app->forgetScopedInstances();
});

it('carries both bounds as inputs whatever it shows', function (): void {
    foreach ([[], [PricePart::Fields], [PricePart::Slider], [PricePart::Slider, PricePart::Fields]] as $parts) {
        $document = $this->render($parts);

        foreach ([Hook::PriceMin, Hook::PriceMax] as $hook) {
            $this->assertNotNull($this->one($document, $hook), $hook->value.' is missing without '.count($parts).' parts.');
        }
    }
});

// Declared with no part at all, it still reads the URL — so it keeps its inputs, hidden.
it('hides its inputs when it shows nothing', function (): void {
    expect($this->one($this->render([]), Hook::PriceMin)->getAttribute('type'))->toBe('hidden');
    expect($this->one($this->render([PricePart::Fields]), Hook::PriceMin)->getAttribute('type'))->toBe('number');
});

// Alone, an empty field is an open end; beside the control it mirrors the handle.
it('leaves a field empty when nothing was asked', function (): void {
    expect($this->one($this->render([PricePart::Fields]), Hook::PriceMin)->getAttribute('value'))->toBe('');
    expect($this->one($this->render([PricePart::Slider, PricePart::Fields]), Hook::PriceMin)->getAttribute('value'))->not->toBe('');
});

it('hints each field with the end of the catalogue it stands for', function (): void {
    $document = $this->render([PricePart::Fields]);
    $min = $this->one($document, Hook::PriceMin);
    $max = $this->one($document, Hook::PriceMax);

    expect($min->getAttribute('placeholder'))->toBe($min->getAttribute('min'));
    expect($max->getAttribute('placeholder'))->toBe($max->getAttribute('max'));
    expect($max->getAttribute('placeholder'))->not->toBe($min->getAttribute('placeholder'));
});

it('shows back what the url asked for', function (): void {
    $document = $this->render([PricePart::Fields], ['min_price' => '40', 'max_price' => '70']);

    expect($this->one($document, Hook::PriceMin)->getAttribute('value'))->toBe('40');
    expect($this->one($document, Hook::PriceMax)->getAttribute('value'))->toBe('70');
});

// Without it the stylesheet falls back to `--from: 0` and `--to: 1`, and the
// whole track reads as selected until a script runs.
it('draws the filled part of the track before any script runs', function (): void {
    [$lowest, $highest] = $this->catalogueBounds();
    $quarter = ($highest - $lowest) / 4;
    $document = $this->render([PricePart::Slider], [
        'min_price' => (string) ($lowest + $quarter),
        'max_price' => (string) ($highest - $quarter),
    ]);
    $style = $this->one($document, Hook::PriceRange)->getAttribute('style');

    expect((string) $style)->toMatch('/--from: 0\.\d+/');
    expect((string) $style)->toMatch('/--to: 0\.\d+/');
});

// A field carries a bare number, so a control without a range formats nothing:
// `wc_price()` reads the currency, the decimals and both separators each time.
it('formats no price when it draws no range', function (): void {
    expect($this->render([PricePart::Fields])->body->innerHTML)->not->toContain('aria-valuetext');
    expect($this->render([PricePart::Slider])->body->innerHTML)->toContain('aria-valuetext');
});

it('renders hidden and empty when the engine reports no bounds', function (): void {
    $this->app->scoped(SearchEngine::class, fn (): FakeSearchEngine => new FakeSearchEngine);

    $document = $this->render([PricePart::Slider, PricePart::Fields]);

    expect($this->one($document, Hook::Facet)?->hasAttribute('hidden'))->toBeTrue();

    foreach ([Hook::PriceMin, Hook::PriceMax] as $hook) {
        $input = $this->one($document, $hook);

        $this->assertNotNull($input, $hook->value.' left the markup with no bounds.');
        expect($input->getAttribute('value'))->toBe('', $hook->value.' invented a bound.');
    }
});

// It is placed like a facet, but a fold button folds values, and a range has none.
it('is a facet host with nothing to fold', function (): void {
    $facet = $this->one($this->render([PricePart::Fields]), Hook::Facet);

    expect($facet)->not->toBeNull();
    expect($this->one($facet, Hook::More))->toBeNull();
    expect($this->one($facet, Hook::FacetValue))->toBeNull();
});

it('refuses to place a term facet', function (): void {
    $this->expectExceptionMessageMatches('/place it with the component of its own kind/');

    Blade::render('<x-meilifacets::listing.price name="products" facet="'.$this->aFacetName().'" />');
});
