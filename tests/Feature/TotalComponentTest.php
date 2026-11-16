<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Modules\MeiliFacets\View\CountLabel;
use Tests\TestCase;

trait TotalComponentHelpers
{
    private const string NOTHING_MATCHES = 'qqxxzzww-aucun-produit-ne-correspond';

    private function renderComponent(): string
    {
        return Blade::render('<x-meilifacets::listing.total name="products" />');
    }

    private function renderView(int $count): string
    {
        return (string) view('meilifacets::components.listing.total', [
            'label' => $this->app->make(CountLabel::class)->of(__(':count item|:count items'), $count),
            'attributes' => new ComponentAttributeBag,
            'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
        ])->render();
    }
}

/** The count the server found, in words, where a screen reader hears it change. */
uses(TestCase::class, SwitchesTheSiteLocale::class, TotalComponentHelpers::class);

/** The suite shares one application: a listing resolved here would reach a later class. */
beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->query->replace([]);
    $this->app->forgetScopedInstances();
});

it('counts what the listing found', function (): void {
    $total = $this->app->make(CurrentListing::class)->named(ProductListing::NAME)->total();

    expect($total)->toBeGreaterThan(1);
    expect($this->underLocales('en', 'en_US', $this->renderComponent(...)))->toContain('>'.$total.' items<');
});

/** Rendered at zero too: the client rewrites the region, it never creates one. */
it('speaks the language wordpress translates in even when nothing matches', function (): void {
    request()->query->replace([$this->app->make(UrlParameters::class)->reserved(QueryParameter::Query) => self::NOTHING_MATCHES]);

    expect($this->underLocales('fr', 'en_US', $this->renderComponent(...)))->toContain('>0 items<');
    expect($this->underLocales('fr', 'fr_FR', $this->renderComponent(...)))->toContain('>0 article<');
});

it('agrees with what it counts', function (): void {
    expect($this->underLocales('fr', 'fr_FR', fn (): string => $this->renderView(1)))->toContain('>1 article<');
    expect($this->underLocales('fr', 'fr_FR', fn (): string => $this->renderView(88)))->toContain('>88 articles<');
});

/**
 * The counter is rewritten at every answer, its region only once typing rests: announced when it
 * changes, and whole — « 88 », read alone, names nothing. Empty at first, nothing is said on load.
 */
it('announces itself politely and whole in a region of its own', function (): void {
    [$counter, $region] = array_slice(explode('<p ', $this->renderView(3)), 1);

    expect($counter)->toContain(Hook::Total->attribute()->toHtml());
    expect($counter)->not->toContain('aria-live');
    expect($region)->toMatch(
        '/aria-live="polite" aria-atomic="true" '.preg_quote(Hook::TotalStatus->attribute()->toHtml(), '/').'><\/p>/',
    );
});

it('keeps the classes a theme adds', function (): void {
    expect(Blade::render('<x-meilifacets::listing.total name="products" class="ml-auto" />'))->toContain(
        'class="meilifacetsTotal ml-auto"',
    );
});

/** A translation catalogue is written by hand, and a theme can override it. */
it('escapes its label', function (): void {
    $html = (string) view('meilifacets::components.listing.total', [
        'label' => '<script>alert(1)</script>',
        'attributes' => new ComponentAttributeBag,
        'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
    ])->render();

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script&gt;');
});
