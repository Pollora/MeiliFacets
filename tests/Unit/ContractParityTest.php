<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use FilesystemIterator;
use Generator;
use Modules\MeiliFacets\Enums\ActiveValueKind;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\View\Components\Drawer;
use Modules\MeiliFacets\View\ListingScript;
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

    #[Test]
    public function both_sides_name_the_script_module_the_same(): void
    {
        $this->assertStringContainsString("'".ListingScript::MODULE."'", $this->read('listing-page.ts'));
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
        yield 'facet field prefix' => ['shared/description.ts', "FACET_FIELD_PREFIX = '([^']*)'", DocumentField::Facets->value.'.'];
        yield 'value separator' => ['listing/listing-state.ts', "VALUE_SEPARATOR = '([^']*)'", StateReader::VALUE_SEPARATOR];
        yield 'query bound' => ['listing/listing-state.ts', 'MAX_QUERY_LENGTH = (\d+)', (string) StateReader::MAX_QUERY_LENGTH];
        yield 'first page' => ['listing/listing-state.ts', 'FIRST_PAGE = (\d+)', (string) ListingState::FIRST_PAGE];
        yield 'term pill kind' => ['listing/active-value-list.ts', "TERM_KIND = '([^']*)'", ActiveValueKind::Term->value];
        yield 'price pill kind' => ['listing/active-value-list.ts', "PRICE_KIND = '([^']*)'", ActiveValueKind::Price->value];
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
        preg_match("/ACTIVE_OPTION = '([^']*)'/", $this->read('sort/sort-combobox.ts'), $found);

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
        $stylesheet = (string) file_get_contents(self::STYLESHEET);

        preg_match('/[0-9.]+em/', Drawer::MOBILE, $threshold);
        preg_match_all('/\(width\s*[<>]=?\s*([0-9.]+em)\)/', $stylesheet, $widths);

        $this->assertStringContainsString('@media (scripting: enabled) and '.Drawer::MOBILE.' {', $stylesheet);
        $this->assertNotEmpty($widths[1]);
        $this->assertSame([$threshold[0]], array_values(array_unique($widths[1])), 'A second threshold would drift from the one the drawer reads.');
    }

    /** Escape closes at once: the client marks it, and only the stylesheet cuts the transition. */
    #[Test]
    public function the_stylesheet_cuts_the_motion_the_client_marks_as_instant(): void
    {
        preg_match("/INSTANT = '([^']*)'/", $this->read('shared/attributes.ts'), $found);

        $this->assertNotSame('', $found[1] ?? '');
        $this->assertStringContainsString('['.$found[1].']', (string) file_get_contents(self::STYLESHEET));
    }

    /**
     * @return list<string>
     */
    private function hooksAddressedByTheClient(): array
    {
        $source = implode('', array_map($this->read(...), $this->clientFiles()))
            .(string) file_get_contents(self::STYLESHEET);

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
