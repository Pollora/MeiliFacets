<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\Tests\Feature\Concerns\PinsIndexedPostTypes;
use Tests\TestCase;

trait SearchComponentSteps
{
    private function root(string $name): SearchRoot
    {
        return $this->app->make(SearchRegistry::class)->named($name);
    }
}

uses(TestCase::class, PinsIndexedPostTypes::class, SearchComponentSteps::class);

beforeEach(function (): void {
    $this->pinIndexedPostTypes();
});

// The registry is scoped: roots rendered here would reach the next test of the shared application.
afterEach(function (): void {
    $this->unpinIndexedPostTypes();
    $this->app->forgetScopedInstances();
});

it('renders a container the client can find by name', function (): void {
    $html = Blade::render('<x-meilifacets::search class="header-search">slot</x-meilifacets::search>');

    expect($html)->toContain('data-meili="search"');
    expect($html)->toContain('data-search="'.SearchRoot::DEFAULT_NAME.'"');
    expect($html)->toContain((string) Contract::version());
    expect($html)->toContain('class="header-search"');
    expect($html)->toContain('slot');
});

it('takes its settings from the container', function (): void {
    Blade::render('<x-meilifacets::search />');

    expect($this->root(SearchRoot::DEFAULT_NAME)->settings)->toEqual(new SearchSettings);
});

it('lets a template override the threshold and the delay', function (): void {
    Blade::render('<x-meilifacets::search min-chars="3" delay="250" />');

    expect($this->root(SearchRoot::DEFAULT_NAME)->settings)->toEqual(new SearchSettings(3, 250));
});

it('lets a project change the defaults everywhere', function (): void {
    $binding = $this->app->getBindings()[SearchSettings::class];
    $this->app->bind(SearchSettings::class, static fn (): SearchSettings => new SearchSettings(3, 300, 8));

    try {
        Blade::render('<x-meilifacets::search delay="50" />');

        expect($this->root(SearchRoot::DEFAULT_NAME)->settings)->toEqual(new SearchSettings(3, 50, 8));
    } finally {
        $this->app->bind(SearchSettings::class, $binding['concrete'], $binding['shared']);
    }
});

it('lets two roots of different names share a page', function (): void {
    $html = Blade::render('<x-meilifacets::search name="header" /><x-meilifacets::search name="footer" min-chars="4" />');

    expect($html)->toContain('data-search="header"');
    expect($html)->toContain('data-search="footer"');
    expect($this->root('header')->settings->minChars)->toBe(2);
    expect($this->root('footer')->settings->minChars)->toBe(4);
});

it('refuses two roots under one name', function (): void {
    $this->expectException(ViewException::class);
    $this->expectExceptionMessage('A search named "search" is already rendered');

    Blade::render('<x-meilifacets::search /><x-meilifacets::search />');
});

// What the automatic sections of step 5 will iterate over.
it('holds the accepted types in their declared order', function (): void {
    Blade::render('<x-meilifacets::search />');

    expect(array_keys($this->root(SearchRoot::DEFAULT_NAME)->types))->toBe(array_keys($this->app->make(AcceptedSearchTypes::class)->all()));
    expect($this->root(SearchRoot::DEFAULT_NAME)->types)->not->toBe([]);
});
