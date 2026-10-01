<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use FilesystemIterator;
use Generator;
use Modules\MeiliFacets\Enums\ActiveValueKind;
use Modules\MeiliFacets\Enums\BindingAttribute;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\View\Components\Listing\Drawer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The same contract is written twice, once per language. Nothing but this makes
 * the two copies fail together rather than diverge in silence.
 */
final class ContractParityTest extends TestCase
{
    private const string CLIENT = __DIR__.'/../../resources/assets/ts';

    private const string STYLESHEET = __DIR__.'/../../resources/assets/css/meilifacets.css';

    private const string SEARCH_STYLESHEET = __DIR__.'/../../resources/assets/css/site-search.css';

    private const string VIEWS = __DIR__.'/../../resources/views/components';

    #[Test]
    public function both_sides_claim_the_same_contract_version(): void
    {
        preg_match('/const VERSION = (\d+)/', $this->read('shared/contract.ts'), $found);

        $this->assertSame((string) Contract::VERSION, $found[1] ?? '', 'contract.ts and Contract::VERSION disagree.');
    }

    /** The client compares this attribute: written under the wrong name, it refuses to start on every listing. */
    #[Test]
    public function the_root_announces_its_version_where_the_client_reads_it(): void
    {
        preg_match("/VERSION_ATTRIBUTE = '([^']*)'/", $this->read('shared/contract.ts'), $attribute);
        preg_match('/const VERSION = (\d+)/', $this->read('shared/contract.ts'), $version);

        $this->assertSame(($attribute[1] ?? '').'="'.($version[1] ?? '').'"', (string) Contract::version());
    }

    /** A hook the client addresses and PHP does not declare is a hook nothing renders. */
    #[Test]
    public function every_hook_the_client_addresses_is_declared_in_php(): void
    {
        $declared = array_column(Hook::cases(), 'value');

        foreach ($this->hooksAddressedByTheClient() as $hook) {
            $this->assertContains($hook, $declared, "The client addresses \"{$hook}\", which Hook does not declare.");
        }
    }

    #[Test]
    public function the_client_addresses_the_hooks_the_contract_requires(): void
    {
        $this->assertNotEmpty($this->hooksAddressedByTheClient());
        $this->assertContains('results', $this->hooksAddressedByTheClient());
    }

    /**
     * The loader of each bundle, which reads the data published under the module's id.
     *
     * @return Generator<string, array{ScriptModule, string}>
     */
    public static function loaders(): Generator
    {
        yield 'listing' => [ScriptModule::Listing, 'listing-page.ts'];
        yield 'site search' => [ScriptModule::SiteSearch, 'site-search-page.ts'];
    }

    #[DataProvider('loaders')]
    #[Test]
    public function both_sides_name_the_script_module_the_same(ScriptModule $module, string $loader): void
    {
        $this->assertStringContainsString("id: '".$module->value."'", $this->read($loader));
    }

    #[DataProvider('loaders')]
    #[Test]
    public function both_sides_file_the_descriptions_under_the_same_key(ScriptModule $module, string $loader): void
    {
        $this->assertStringContainsString("roots: '".$module->roots()."'", $this->read($loader));
    }

    /** The bundle PHP inscribes is the one `bundle.ts` writes from that loader. */
    #[DataProvider('loaders')]
    #[Test]
    public function the_inscribed_bundle_is_built_from_its_loader(ScriptModule $module, string $loader): void
    {
        $bundle = basename($module->source());

        $this->assertStringContainsString("new Bundle('{$loader}', '{$bundle}')", (string) file_get_contents(__DIR__.'/../../bundle.ts'));
    }

    /**
     * The client finds a root by this attribute: rendered under another name, the root is served and never bound.
     *
     * @return Generator<string, array{string, string}>
     */
    public static function roots(): Generator
    {
        yield 'listing' => ['LISTING_ATTRIBUTE', 'listing'];
        yield 'search' => ['SEARCH_ATTRIBUTE', 'search'];
    }

    #[DataProvider('roots')]
    #[Test]
    public function every_root_is_rendered_under_the_attribute_the_client_finds_it_by(string $constant, string $view): void
    {
        preg_match("/{$constant} = '([^']*)'/", $this->read('shared/root-component.ts'), $found);

        $this->assertNotSame('', $found[1] ?? '');
        $this->assertStringContainsString(($found[1] ?? '').'="', (string) file_get_contents(self::VIEWS."/{$view}.blade.php"));
    }

    /**
     * Values written on both sides. Nothing forces them there — `cap` and
     * `reachableHits` travel in the description — but an attribute name cannot,
     * and the rest is arithmetic no description should have to carry.
     *
     * @return Generator<string, array{string, string, string}>
     */
    public static function twins(): Generator
    {
        yield 'markup attribute' => ['shared/contract.ts', "ATTRIBUTE = '([^']*)'", Contract::Attribute->value];
        yield 'version attribute' => ['shared/contract.ts', "VERSION_ATTRIBUTE = '([^']*)'", Contract::VersionAttribute->value];
        yield 'scroll attribute' => ['shared/contract.ts', "SCROLL_ATTRIBUTE = '([^']*)'", Contract::ScrollAttribute->value];
        yield 'retrieved id' => ['site-search/site-search-query.ts', "RETRIEVED = \\['([^']*)'", DocumentField::Id->value];
        yield 'card id field' => ['results/card-view.ts', "ID_FIELD = '([^']*)'", CardField::Id->value];
        yield 'retrieved card' => ['site-search/site-search-query.ts', "RETRIEVED = \\['ID', '([^']*)'\\]", DocumentField::Card->value];
        yield 'search brick prefix' => ['shared/root-component.ts', "SEARCH_PREFIX = '([^']*)'", Hook::Search->value];
        yield 'facet field prefix' => ['shared/description.ts', "FACET_FIELD_PREFIX = '([^']*)'", DocumentField::Facets->value.'.'];
        yield 'value separator' => ['listing/listing-state.ts', "VALUE_SEPARATOR = '([^']*)'", StateReader::VALUE_SEPARATOR];
        yield 'query bound' => ['listing/listing-state.ts', 'MAX_QUERY_LENGTH = (\d+)', (string) StateReader::MAX_QUERY_LENGTH];
        yield 'first page' => ['listing/listing-state.ts', 'FIRST_PAGE = (\d+)', (string) ListingState::FIRST_PAGE];
        yield 'search pill kind' => ['listing/active-value-list.ts', "SEARCH_KIND = '([^']*)'", ActiveValueKind::Search->value];
        yield 'term pill kind' => ['listing/active-value-list.ts', "TERM_KIND = '([^']*)'", ActiveValueKind::Term->value];
        yield 'price pill kind' => ['listing/active-value-list.ts', "PRICE_KIND = '([^']*)'", ActiveValueKind::Price->value];
        yield 'text binding' => ['results/card-binding.ts', "TEXT_BINDING = '([^']*)'", BindingAttribute::Text->value];
        yield 'attribute binding' => ['results/card-binding.ts', "ATTRIBUTE_BINDING = '([^']*)'", BindingAttribute::Attribute->value];
        yield 'class binding' => ['results/card-binding.ts', "CLASS_BINDING = '([^']*)'", BindingAttribute::ClassName->value];
        yield 'condition binding' => ['results/card-binding.ts', "CONDITION_BINDING = '([^']*)'", BindingAttribute::Condition->value];
        yield 'binding pair separator' => ['results/card-binding.ts', "PAIR_SEPARATOR = '([^']*)'", BindingAttribute::PAIR_SEPARATOR];
        yield 'binding negation' => ['results/card-binding.ts', "NEGATION = '([^']*)'", BindingAttribute::NEGATION];
        yield 'binding list separator' => ['results/card-binding.ts', "LIST_SEPARATOR = '([^']*)'", BindingAttribute::LIST_SEPARATOR];
        yield 'binding fallback separator' => ['results/card-binding.ts', "FALLBACK_SEPARATOR = '([^']*)'", BindingAttribute::FALLBACK_SEPARATOR];
    }

    #[DataProvider('twins')]
    #[Test]
    public function both_sides_hold_the_same_value(string $file, string $pattern, string $expected): void
    {
        preg_match('/'.$pattern.'/', $this->read($file), $found);

        $this->assertSame($expected, $found[1] ?? '', "{$file} and PHP disagree.");
    }

    /** The client writes it, the stylesheet marks the keyboard with it, and no hook covers it. */
    #[Test]
    public function the_stylesheet_marks_the_option_the_client_points_at(): void
    {
        preg_match("/ACTIVE_OPTION = '([^']*)'/", $this->read('shared/attributes.ts'), $found);

        $this->assertNotSame('', $found[1] ?? '');
        $this->assertStringContainsString('['.$found[1].']', (string) file_get_contents(self::STYLESHEET));
    }

    /** UX-2: the client marks a panel that would overflow, and only the stylesheet moves it. */
    #[Test]
    public function the_stylesheet_moves_the_panel_the_client_aligns_on_its_end(): void
    {
        preg_match("/ALIGNED_TO_END = '([^']*)'/", $this->read('collapsible/disclosure-group.ts'), $found);

        $this->assertNotSame('', $found[1] ?? '');
        $this->assertStringContainsString('['.$found[1].']', (string) file_get_contents(self::STYLESHEET));
    }

    /** Q-3: the client promotes the drawer where `media` holds, the stylesheet draws the sheet where its query does. */
    #[Test]
    public function the_stylesheet_draws_the_sheet_where_the_drawer_promotes_it_by_default(): void
    {
        $this->assertStringContainsString(
            '@media (scripting: enabled) and '.Drawer::MOBILE.' {',
            (string) file_get_contents(self::STYLESHEET)
        );
    }

    /**
     * S-10: the search panel turns from a full-height sheet into a capped panel where the drawer turns into a bar.
     *
     * @return Generator<string, array{string}>
     */
    public static function stylesheets(): Generator
    {
        yield 'listing' => [self::STYLESHEET];
        yield 'site search' => [self::SEARCH_STYLESHEET];
    }

    #[DataProvider('stylesheets')]
    #[Test]
    public function every_stylesheet_breaks_where_the_drawer_does(string $path): void
    {
        preg_match('/[0-9.]+em/', Drawer::MOBILE, $threshold);
        preg_match_all('/\(width\s*[<>]=?\s*([0-9.]+em)\)/', (string) file_get_contents($path), $widths);

        $this->assertNotEmpty($widths[1]);
        $this->assertSame([$threshold[0]], array_values(array_unique($widths[1])), 'A second threshold would drift from the one the drawer reads.');
    }

    /**
     * The client reads a section's type and limit on the element the view renders.
     *
     * @return Generator<string, array{string}>
     */
    public static function sectionAttributes(): Generator
    {
        yield 'type' => ['TYPE_ATTRIBUTE'];
        yield 'limit' => ['LIMIT_ATTRIBUTE'];
    }

    #[DataProvider('sectionAttributes')]
    #[Test]
    public function the_section_renders_what_the_client_reads_on_it(string $constant): void
    {
        preg_match("/{$constant} = '([^']*)'/", $this->read('site-search/section-view.ts'), $found);

        $this->assertNotSame('', $found[1] ?? '');
        $this->assertStringContainsString(($found[1] ?? '').'="', (string) file_get_contents(self::VIEWS.'/search/section.blade.php'));
    }

    /** Escape closes at once: the client marks it, and only the stylesheet cuts the transition. */
    #[Test]
    public function the_stylesheet_cuts_the_motion_the_client_marks_as_instant(): void
    {
        preg_match("/INSTANT = '([^']*)'/", $this->read('shared/attributes.ts'), $found);

        $this->assertNotSame('', $found[1] ?? '');
        $this->assertStringContainsString('['.$found[1].']', (string) file_get_contents(self::STYLESHEET));
    }

    #[Test]
    public function the_stylesheet_draws_the_scrim_from_the_state_the_client_sets(): void
    {
        preg_match("/OPEN = '([^']*)'/", $this->read('shared/attributes.ts'), $found);

        $this->assertNotSame('', $found[1] ?? '');
        $this->assertStringContainsString('[data-meili="search"]['.$found[1].']::after', (string) file_get_contents(self::SEARCH_STYLESHEET));
    }

    /**
     * @return list<string>
     */
    private function hooksAddressedByTheClient(): array
    {
        $source = implode('', array_map($this->read(...), $this->clientFiles()))
            .(string) file_get_contents(self::STYLESHEET)
            .(string) file_get_contents(self::SEARCH_STYLESHEET);

        // `one`/`all`/`selector` take the hook first, `hookOf` takes the node first.
        preg_match_all("/(?:one|all|selector)\(\s*'([a-z-]+)'/", $source, $calls);
        preg_match_all("/hookOf\([^,]+,\s*'([a-z-]+)'/", $source, $addressed);
        preg_match_all('/data-meili="([a-z-]+)"/', $source, $styled);
        preg_match_all("/(?:host|hooks): (?:'([a-z-]+)'|\[([^\]]*)\])/", $source, $rules);

        $inRules = array_merge(...array_map(
            static fn (string $list): array => preg_split('/[^a-z-]+/', $list, flags: PREG_SPLIT_NO_EMPTY) ?: [],
            $rules[2]
        ));

        return array_values(array_unique(array_filter(
            [...$calls[1], ...$addressed[1], ...$styled[1], ...$rules[1], ...$inRules]
        )));
    }

    /**
     * @return list<string>
     */
    private function clientFiles(): array
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::CLIENT, FilesystemIterator::SKIP_DOTS));
        $client = [];

        foreach ($files as $file) {
            $client[] = substr($file->getPathname(), strlen(self::CLIENT) + 1);
        }

        return $client;
    }

    private function read(string $file): string
    {
        return (string) file_get_contents(self::CLIENT.'/'.$file);
    }
}
