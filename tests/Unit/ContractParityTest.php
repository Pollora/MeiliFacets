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
use Modules\MeiliFacets\Enums\RowOverflow;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Enums\VariantField;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\View\Components\Listing\Drawer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/*
 * The same contract is written twice, once per language. Nothing but this makes
 * the two copies fail together rather than diverge in silence.
 */

const CONTRACT_PARITY_CLIENT = __DIR__.'/../../resources/assets/ts';

const CONTRACT_PARITY_STYLESHEET = __DIR__.'/../../resources/assets/css/meilifacets.css';

const CONTRACT_PARITY_SEARCH_STYLESHEET = __DIR__.'/../../resources/assets/css/site-search.css';

const CONTRACT_PARITY_BROWSER_TEST_HOOKS = __DIR__.'/../playwright/hooks.ts';

const CONTRACT_PARITY_VIEWS = __DIR__.'/../../resources/views/components';

/**
 * @return list<string>
 */
function contractParityHooksAddressedByTheClient(): array
{
    $source = implode('', array_map(contractParityRead(...), contractParityClientFiles()))
        .(string) file_get_contents(CONTRACT_PARITY_STYLESHEET)
        .(string) file_get_contents(CONTRACT_PARITY_SEARCH_STYLESHEET);

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
function contractParityClientFiles(): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CONTRACT_PARITY_CLIENT, FilesystemIterator::SKIP_DOTS));
    $client = [];

    foreach ($files as $file) {
        $client[] = substr($file->getPathname(), strlen(CONTRACT_PARITY_CLIENT) + 1);
    }

    return $client;
}

function contractParityRead(string $file): string
{
    return (string) file_get_contents(CONTRACT_PARITY_CLIENT.'/'.$file);
}

/*
 * The loader of each bundle, which reads the data published under the module's id.
 *
 * @return Generator<string, array{ScriptModule, string}>
 */
dataset('contract parity loaders', function (): Generator {
    yield 'listing' => [ScriptModule::Listing, 'listing-page.ts'];
    yield 'site search' => [ScriptModule::SiteSearch, 'site-search-page.ts'];
});

/*
 * The client finds a root by this attribute: rendered under another name, the root is served and never bound.
 *
 * @return Generator<string, array{string, string}>
 */
dataset('contract parity roots', function (): Generator {
    yield 'listing' => ['LISTING_ATTRIBUTE', 'listing'];
    yield 'search' => ['SEARCH_ATTRIBUTE', 'search'];
});

/*
 * Values written on both sides. Nothing forces them there — `cap` and
 * `reachableHits` travel in the description — but an attribute name cannot,
 * and the rest is arithmetic no description should have to carry.
 *
 * @return Generator<string, array{string, string, string}>
 */
dataset('contract parity twins', function (): Generator {
    yield 'markup attribute' => ['shared/contract.ts', "ATTRIBUTE = '([^']*)'", Contract::Attribute->value];
    yield 'version attribute' => ['shared/contract.ts', "VERSION_ATTRIBUTE = '([^']*)'", Contract::VersionAttribute->value];
    yield 'scroll attribute' => ['shared/contract.ts', "SCROLL_ATTRIBUTE = '([^']*)'", Contract::ScrollAttribute->value];
    yield 'retrieved id' => ['site-search/site-search-query.ts', "RETRIEVED = \\['([^']*)'", DocumentField::Id->value];
    yield 'card id field' => ['results/variant-choice.ts', "ID_FIELD = '([^']*)'", CardField::Id->value];
    yield 'card variants field' => ['results/variant-choice.ts', "VARIANTS_FIELD = '([^']*)'", CardField::Variants->value];
    yield 'several variants field' => ['results/variant-choice.ts', "SEVERAL_FIELD = '([^']*)'", CardField::SeveralVariants->value];
    yield 'variant facets' => ['results/card-variant.ts', "FACETS_FIELD = '([^']*)'", VariantField::Facets->value];
    yield 'variant price' => ['results/card-variant.ts', "PRICE_FIELD = '([^']*)'", VariantField::Price->value];
    yield 'variant fields' => ['results/card-variant.ts', "FIELDS_FIELD = '([^']*)'", VariantField::Fields->value];
    yield 'variant stock' => ['results/card-variant.ts', "IN_STOCK_FIELD = '([^']*)'", VariantField::InStock->value];
    yield 'out of stock field' => ['results/variant-choice.ts', "OUT_OF_STOCK_FIELD = '([^']*)'", CardField::OutOfStock->value];
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
    yield 'class list binding' => ['results/card-binding.ts', "CLASS_LIST_BINDING = '([^']*)'", BindingAttribute::ClassList->value];
    yield 'condition binding' => ['results/card-binding.ts', "CONDITION_BINDING = '([^']*)'", BindingAttribute::Condition->value];
    yield 'binding pair separator' => ['results/card-binding.ts', "PAIR_SEPARATOR = '([^']*)'", BindingAttribute::PAIR_SEPARATOR];
    yield 'binding negation' => ['results/card-binding.ts', "NEGATION = '([^']*)'", BindingAttribute::NEGATION];
    yield 'binding list separator' => ['results/card-binding.ts', "LIST_SEPARATOR = '([^']*)'", BindingAttribute::LIST_SEPARATOR];
    yield 'binding fallback separator' => ['results/card-binding.ts', "FALLBACK_SEPARATOR = '([^']*)'", BindingAttribute::FALLBACK_SEPARATOR];
});

/*
 * S-10: the search panel turns from a full-height sheet into a capped panel where the drawer turns into a bar.
 *
 * @return Generator<string, array{string}>
 */
dataset('contract parity stylesheets', function (): Generator {
    yield 'listing' => [CONTRACT_PARITY_STYLESHEET];
    yield 'site search' => [CONTRACT_PARITY_SEARCH_STYLESHEET];
});

/*
 * The client reads a section's type and limit on the element the view renders.
 *
 * @return Generator<string, array{string}>
 */
dataset('contract parity section attributes', function (): Generator {
    yield 'type' => ['TYPE_ATTRIBUTE'];
    yield 'limit' => ['LIMIT_ATTRIBUTE'];
});

it('has both sides claim the same contract version', function (): void {
    preg_match('/const VERSION = (\d+)/', contractParityRead('shared/contract.ts'), $found);

    $this->assertSame((string) Contract::VERSION, $found[1] ?? '', 'contract.ts and Contract::VERSION disagree.');
});

// The client compares this attribute: written under the wrong name, it refuses to start on every listing.
it('announces the version of the root where the client reads it', function (): void {
    preg_match("/VERSION_ATTRIBUTE = '([^']*)'/", contractParityRead('shared/contract.ts'), $attribute);
    preg_match('/const VERSION = (\d+)/', contractParityRead('shared/contract.ts'), $version);

    expect((string) Contract::version())->toBe(($attribute[1] ?? '').'="'.($version[1] ?? '').'"');
});

// A hook the client addresses and PHP does not declare is a hook nothing renders.
it('declares in php every hook the client addresses', function (): void {
    $declared = array_column(Hook::cases(), 'value');

    foreach (contractParityHooksAddressedByTheClient() as $hook) {
        $this->assertContains($hook, $declared, "The client addresses \"{$hook}\", which Hook does not declare.");
    }
});

it('declares in php every hook the browser tests address', function (): void {
    $declared = array_column(Hook::cases(), 'value');

    preg_match_all("/^\s+\w+: '([a-z-]+)',$/m", (string) file_get_contents(CONTRACT_PARITY_BROWSER_TEST_HOOKS), $hooks);

    expect($hooks[1])->not->toBeEmpty();

    foreach ($hooks[1] as $hook) {
        $this->assertContains($hook, $declared, "The browser tests address \"{$hook}\", which Hook does not declare.");
    }
});

it('has the client read every row overflow the server marks', function (): void {
    foreach (RowOverflow::cases() as $overflow) {
        $this->assertStringContainsString("'{$overflow->value}'", contractParityRead('drawer/drawer-side-sheet.ts'), $overflow->name);
    }
});

it('has the client address the hooks the contract requires', function (): void {
    expect(contractParityHooksAddressedByTheClient())->not->toBeEmpty();
    expect(contractParityHooksAddressedByTheClient())->toContain('results');
});

it('has both sides name the script module the same', function (ScriptModule $module, string $loader): void {
    expect(contractParityRead($loader))->toContain("id: '".$module->value."'");
})->with('contract parity loaders');

it('has both sides file the descriptions under the same key', function (ScriptModule $module, string $loader): void {
    expect(contractParityRead($loader))->toContain("roots: '".$module->roots()."'");
})->with('contract parity loaders');

// The bundle PHP inscribes is the one `bundle.ts` writes from that loader.
it('builds the inscribed bundle from its loader', function (ScriptModule $module, string $loader): void {
    $bundle = basename($module->source());

    expect((string) file_get_contents(__DIR__.'/../../bundle.ts'))->toContain("new Bundle('{$loader}', '{$bundle}')");
})->with('contract parity loaders');

it('renders every root under the attribute the client finds it by', function (string $constant, string $view): void {
    preg_match("/{$constant} = '([^']*)'/", contractParityRead('shared/root-component.ts'), $found);

    expect($found[1] ?? '')->not->toBe('');
    expect((string) file_get_contents(CONTRACT_PARITY_VIEWS."/{$view}.blade.php"))->toContain(($found[1] ?? '').'="');
})->with('contract parity roots');

it('holds the same value on both sides', function (string $file, string $pattern, string $expected): void {
    preg_match('/'.$pattern.'/', contractParityRead($file), $found);

    $this->assertSame($expected, $found[1] ?? '', "{$file} and PHP disagree.");
})->with('contract parity twins');

// The client writes it, the stylesheet marks the keyboard with it, and no hook covers it.
it('marks in the stylesheet the option the client points at', function (): void {
    preg_match("/ACTIVE_OPTION = '([^']*)'/", contractParityRead('shared/attributes.ts'), $found);

    expect($found[1] ?? '')->not->toBe('');
    expect((string) file_get_contents(CONTRACT_PARITY_STYLESHEET))->toContain('['.$found[1].']');
});

// UX-2: the client marks a panel that would overflow, and only the stylesheet moves it.
it('moves in the stylesheet the panel the client aligns on its end', function (): void {
    preg_match("/ALIGNED_TO_END = '([^']*)'/", contractParityRead('collapsible/disclosure-group.ts'), $found);

    expect($found[1] ?? '')->not->toBe('');
    expect((string) file_get_contents(CONTRACT_PARITY_STYLESHEET))->toContain('['.$found[1].']');
});

// Q-3: the client promotes the drawer where `media` holds, the stylesheet draws the sheet where its query does.
it('draws in the stylesheet the sheet where the drawer promotes it by default', function (): void {
    expect((string) file_get_contents(CONTRACT_PARITY_STYLESHEET))->toContain(
        '@media (scripting: enabled) and '.Drawer::MOBILE.' {'
    );
});

it('breaks every stylesheet where the drawer does', function (string $path): void {
    preg_match('/[0-9.]+em/', Drawer::MOBILE, $threshold);
    preg_match_all('/\(width\s*[<>]=?\s*([0-9.]+em)\)/', (string) file_get_contents($path), $widths);

    expect($widths[1])->not->toBeEmpty();
    $this->assertSame([$threshold[0]], array_values(array_unique($widths[1])), 'A second threshold would drift from the one the drawer reads.');
})->with('contract parity stylesheets');

it('renders in the section what the client reads on it', function (string $constant): void {
    preg_match("/{$constant} = '([^']*)'/", contractParityRead('site-search/section-view.ts'), $found);

    expect($found[1] ?? '')->not->toBe('');
    expect((string) file_get_contents(CONTRACT_PARITY_VIEWS.'/search/section.blade.php'))->toContain(($found[1] ?? '').'="');
})->with('contract parity section attributes');

// Escape closes at once: the client marks it, and only the stylesheet cuts the transition.
it('cuts in the stylesheet the motion the client marks as instant', function (): void {
    preg_match("/INSTANT = '([^']*)'/", contractParityRead('shared/attributes.ts'), $found);

    expect($found[1] ?? '')->not->toBe('');
    expect((string) file_get_contents(CONTRACT_PARITY_STYLESHEET))->toContain('['.$found[1].']');
});

it('draws in the stylesheet the scrim from the state the client sets', function (): void {
    preg_match("/OPEN = '([^']*)'/", contractParityRead('shared/attributes.ts'), $found);

    expect($found[1] ?? '')->not->toBe('');
    expect((string) file_get_contents(CONTRACT_PARITY_SEARCH_STYLESHEET))->toContain('[data-meili="search"]['.$found[1].']::after');
});
