<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Modules\MeiliFacets\View\CountLabel;
use Tests\TestCase;

trait ActiveFiltersComponentHelpers
{
    private function render(int $count): string
    {
        return (string) view('meilifacets::components.listing.active-filters', [
            'count' => $count,
            'label' => $this->app->make(CountLabel::class)->of(__(':count active filter|:count active filters'), $count),
            'hook' => fn (string $name): HtmlString => new HtmlString('data-meili="'.$name.'"'),
        ])->render();
    }
}

/** A bare digit names nothing: the badge has to say what it counts. */
uses(TestCase::class, SwitchesTheSiteLocale::class, ActiveFiltersComponentHelpers::class);

/** The suite shares one application: a listing resolved here would reach a later class. */
beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    $this->app->forgetScopedInstances();
});

it('names what it counts', function (): void {
    // A word after the digit, whatever the language the site runs in.
    expect($this->render(1))->toMatch('/>1 \w+/u');
    expect($this->render(2))->toMatch('/>2 \w+/u');
});

it('agrees with what it counts', function (): void {
    // Kept as `assertNotSame`: it renders the expected side first, as the original did.
    $this->assertNotSame(strip_tags($this->render(1)), strip_tags($this->render(2)));
    $this->assertNotSame(
        preg_replace('/\d/', '', strip_tags($this->render(1))),
        preg_replace('/\d/', '', strip_tags($this->render(2)))
    );
});

it('resolves as a component and speaks the language wordpress translates in', function (): void {
    $html = $this->underLocales('fr', 'en_US', fn (): string => Blade::render('<x-meilifacets::listing.active-filters name="products" />'));

    expect($html)->toContain('0 active filters');
});

/** Rendered even at zero: the client reveals it, it creates nothing. */
it('is rendered and hidden when nothing is filtered', function (): void {
    $html = $this->render(0);

    expect($html)->toContain('hidden');
    expect($html)->toContain('data-meili="active-filters"');
});
