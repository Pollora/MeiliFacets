<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\View\Compilers\ComponentTagCompiler;
use InvalidArgumentException;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Providers\MeiliFacetsServiceProvider;
use Modules\MeiliFacets\Tests\Feature\Concerns\PinsIndexedPostTypes;
use Modules\MeiliFacets\View\Components\Listing\Facets;
use Modules\MeiliFacets\View\Components\Listing\Search as ListingSearch;
use Modules\MeiliFacets\View\Components\Search;
use Modules\MeiliFacets\View\Components\Search\EmptyState;
use Modules\MeiliFacets\View\Components\Search\Toggle;
use ReflectionFunction;
use Tests\TestCase;

trait ComponentFoldersHelpers
{
    private const string NAMESPACE = 'meilifacets';

    private string $theme = '';

    /** @var array<string, list<string>> */
    private array $hints = [];

    private function overrideView(string $view, string $override): void
    {
        $this->theme = sys_get_temp_dir().'/meilifacets-theme-'.bin2hex(random_bytes(4));
        $path = $this->theme.'/resources/views/modules/meilifacets/components/'.$view.'.blade.php';
        new Filesystem()->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $override);

        add_filter('stylesheet_directory', fn (): string => $this->theme);
        ($this->themeOverride())();
    }

    private function compiler(): ComponentTagCompiler
    {
        return new ComponentTagCompiler(Blade::getClassComponentAliases(), Blade::getClassComponentNamespaces(), Blade::getFacadeRoot());
    }

    /** The provider's own hook, run alone: the whole of `after_setup_theme` would set the theme up a second time. */
    private function themeOverride(): Closure
    {
        global $wp_filter;

        foreach ($wp_filter['after_setup_theme']->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'];

                if ($function instanceof Closure && new ReflectionFunction($function)->getClosureScopeClass()?->getName() === MeiliFacetsServiceProvider::class) {
                    return $function;
                }
            }
        }

        $this->fail('The module hooks nothing on after_setup_theme.');
    }
}

/** Each root keeps its bricks in its own folder, class and view alike, reached by Laravel's dot notation. */
uses(TestCase::class, PinsIndexedPostTypes::class, ComponentFoldersHelpers::class);

beforeEach(function (): void {
    $this->pinIndexedPostTypes();
    $this->hints = View::getFinder()->getHints();
});

/** The registry is scoped and the finder is shared: both would reach the next test of the application. */
afterEach(function (): void {
    remove_all_filters('stylesheet_directory');
    View::getFinder()->replaceNamespace(self::NAMESPACE, $this->hints[self::NAMESPACE] ?? []);
    View::getFinder()->flush();

    if ($this->theme !== '') {
        new Filesystem()->deleteDirectory($this->theme);
    }

    $this->unpinIndexedPostTypes();
    $this->app->forgetScopedInstances();
});

it('resolves a dotted tag to the class of its folder before the view', function (string $tag, string $resolved): void {
    expect($this->compiler()->componentClass($tag))->toBe($resolved);
})->with(function (): iterable {
    yield 'a search brick' => ['meilifacets::search.toggle', Toggle::class];
    yield 'a listing brick' => ['meilifacets::listing.facets', Facets::class];
    yield 'a brick whose name PHP reserves' => ['meilifacets::search.empty-state', EmptyState::class];
    yield 'a listing brick named like the search root' => ['meilifacets::listing.search', ListingSearch::class];
    yield 'the search root beside that listing brick' => ['meilifacets::search', Search::class];
    yield 'a brick with no class' => ['meilifacets::listing.toggle', 'meilifacets::components.listing.toggle'];
});

it('resolves no former or reserved name', function (string $tag): void {
    $this->expectException(InvalidArgumentException::class);

    $this->compiler()->componentClass($tag);
})->with(function (): iterable {
    yield 'a search brick' => ['meilifacets::search-toggle'];
    yield 'a listing brick' => ['meilifacets::facets'];
    yield 'the empty state under the reserved word' => ['meilifacets::search.empty'];
    yield 'the search field of the listing' => ['meilifacets::listing.query-field'];
});

it('renders a search brick by its dotted name', function (): void {
    $html = Blade::render('<x-meilifacets::search><x-meilifacets::search.toggle /></x-meilifacets::search>');

    expect($html)->toContain(Hook::SearchToggle->attribute()->toHtml());
});

it('renders a listing brick by its dotted name', function (): void {
    expect(Blade::render('<x-meilifacets::listing.facets name="products" />'))->toContain(Hook::Facets->attribute()->toHtml());
});

it('takes each search view a theme overrides under the folder of its root', function (string $view, string $override): void {
    $this->overrideView($view, $override);

    expect(Blade::render('<x-meilifacets::search />'))->toContain('themedSearch');
})->with(function (): iterable {
    yield 'the root' => ['search', '<div class="themedSearch" {{ $hook(\'search\') }}>{{ $slot }}</div>'];

    foreach (['toggle', 'panel', 'input', 'section', 'card', 'empty-state', 'unavailable'] as $brick) {
        yield 'the '.$brick => ['search/'.$brick, '<div class="themedSearch" {{ $attributes }}>{{ $slot ?? \'\' }}</div>'];
    }
});

it('takes the search field a theme overrides under its name', function (): void {
    $this->overrideView('listing/search', '<form class="themedListingSearch"></form>');

    expect(Blade::render('<x-meilifacets::listing.search name="products" />'))->toContain('themedListingSearch');
});
