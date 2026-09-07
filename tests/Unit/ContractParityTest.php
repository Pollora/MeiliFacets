<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Generator;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\View\ListingScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The same contract is written twice, once per language. Nothing but this makes
 * the two copies fail together rather than diverge in silence.
 */
final class ContractParityTest extends TestCase
{
    private const string CLIENT = __DIR__.'/../../resources/assets/js';

    private const string STYLESHEET = __DIR__.'/../../resources/assets/css/meilifacets.css';

    #[Test]
    public function both_sides_claim_the_same_contract_version(): void
    {
        preg_match('/const VERSION = (\d+)/', $this->read('contract.js'), $found);

        $this->assertSame((string) Contract::VERSION, $found[1] ?? '', 'contract.js and Contract::VERSION disagree.');
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
        $this->assertStringContainsString("'".ListingScript::MODULE."'", $this->read('listing-page.js'));
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
        yield 'markup attribute' => ['contract.js', "ATTRIBUTE = '([^']*)'", Contract::ATTRIBUTE];
        yield 'version attribute' => ['contract.js', "VERSION_ATTRIBUTE = '([^']*)'", Contract::VERSION_ATTRIBUTE];
        yield 'facet field prefix' => ['description.js', "FACET_FIELD_PREFIX = '([^']*)'", DocumentField::Facets->value.'.'];
        yield 'value separator' => ['listing-state.js', "VALUE_SEPARATOR = '([^']*)'", StateReader::VALUE_SEPARATOR];
        yield 'query bound' => ['listing-state.js', 'MAX_QUERY_LENGTH = (\d+)', (string) StateReader::MAX_QUERY_LENGTH];
        yield 'first page' => ['listing-state.js', 'FIRST_PAGE = (\d+)', (string) ListingState::FIRST_PAGE];
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
        preg_match("/ACTIVE_OPTION = '([^']*)'/", $this->read('sort-combobox.js'), $found);

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
        return array_map(basename(...), glob(self::CLIENT.'/*.js') ?: []);
    }

    private function read(string $file): string
    {
        return (string) file_get_contents(self::CLIENT.'/'.$file);
    }
}
