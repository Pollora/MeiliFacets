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
use Modules\MeiliFacets\View\Components\Listing\Facets;
use Modules\MeiliFacets\View\Components\Search\EmptyState;
use Modules\MeiliFacets\View\Components\Search\Toggle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionFunction;
use Tests\TestCase;

/** Each root keeps its bricks in its own folder, class and view alike, reached by Laravel's dot notation. */
final class ComponentFoldersTest extends TestCase
{
    use PinsIndexedPostTypes;

    private const string NAMESPACE = 'meilifacets';

    private string $theme = '';

    /** @var array<string, list<string>> */
    private array $hints = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinIndexedPostTypes();
        $this->hints = View::getFinder()->getHints();
    }

    /** The registry is scoped and the finder is shared: both would reach the next test of the application. */
    protected function tearDown(): void
    {
        remove_all_filters('stylesheet_directory');
        View::getFinder()->replaceNamespace(self::NAMESPACE, $this->hints[self::NAMESPACE] ?? []);
        View::getFinder()->flush();

        if ($this->theme !== '') {
            new Filesystem()->deleteDirectory($this->theme);
        }

        $this->unpinIndexedPostTypes();
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tags(): iterable
    {
        yield 'a search brick' => ['meilifacets::search.toggle', Toggle::class];
        yield 'a listing brick' => ['meilifacets::listing.facets', Facets::class];
        yield 'a brick whose name PHP reserves' => ['meilifacets::search.empty-state', EmptyState::class];
        yield 'a brick with no class' => ['meilifacets::listing.toggle', 'meilifacets::components.listing.toggle'];
    }

    #[DataProvider('tags')]
    #[Test]
    public function it_resolves_a_dotted_tag_to_the_class_of_its_folder_before_the_view(string $tag, string $resolved): void
    {
        $this->assertSame($resolved, $this->compiler()->componentClass($tag));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formerTags(): iterable
    {
        yield 'a search brick' => ['meilifacets::search-toggle'];
        yield 'a listing brick' => ['meilifacets::facets'];
        yield 'the empty state under the reserved word' => ['meilifacets::search.empty'];
    }

    #[DataProvider('formerTags')]
    #[Test]
    public function it_resolves_no_former_or_reserved_name(string $tag): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->compiler()->componentClass($tag);
    }

    #[Test]
    public function it_renders_a_search_brick_by_its_dotted_name(): void
    {
        $html = Blade::render('<x-meilifacets::search><x-meilifacets::search.toggle /></x-meilifacets::search>');

        $this->assertStringContainsString(Hook::SearchToggle->attribute()->toHtml(), $html);
    }

    #[Test]
    public function it_renders_a_listing_brick_by_its_dotted_name(): void
    {
        $this->assertStringContainsString(Hook::Facets->attribute()->toHtml(), Blade::render('<x-meilifacets::listing.facets />'));
    }

    #[Test]
    public function it_takes_the_search_card_a_theme_overrides_under_the_folder_of_its_root(): void
    {
        $this->theme = sys_get_temp_dir().'/meilifacets-theme-'.bin2hex(random_bytes(4));
        $override = $this->theme.'/resources/views/modules/meilifacets/components/search/card.blade.php';
        new Filesystem()->ensureDirectoryExists(dirname($override));
        file_put_contents($override, '<a class="themedSearchCard" href="" {{ $hook(\'card\') }}></a>');

        add_filter('stylesheet_directory', fn (): string => $this->theme);
        ($this->themeOverride())();

        $html = Blade::render('<x-meilifacets::search />');

        $this->assertStringContainsString('themedSearchCard', $html);
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
