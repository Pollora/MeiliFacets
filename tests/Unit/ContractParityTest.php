<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\View\ListingScript;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The same contract is written twice, once per language. Nothing but this makes
 * the two copies fail together rather than diverge in silence.
 */
final class ContractParityTest extends TestCase
{
    private const string CLIENT = __DIR__.'/../../resources/assets/js';

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

    #[Test]
    public function both_sides_prefix_an_unmapped_taxonomy_the_same(): void
    {
        preg_match("/UNMAPPED_PREFIX = '([^']*)'/", $this->read('listing-url.js'), $found);

        $this->assertSame(UrlParameters::UNMAPPED_PREFIX, $found[1] ?? '');
    }

    /**
     * @return list<string>
     */
    private function hooksAddressedByTheClient(): array
    {
        $source = implode('', array_map($this->read(...), $this->clientFiles()));

        preg_match_all("/(?:one|all|selector)\(\s*'([a-z-]+)'/", $source, $calls);
        preg_match_all("/(?:host|hooks): (?:'([a-z-]+)'|\[([^\]]*)\])/", $source, $rules);

        $inRules = array_merge(...array_map(
            static fn (string $list): array => preg_split('/[^a-z-]+/', $list, flags: PREG_SPLIT_NO_EMPTY) ?: [],
            $rules[2]
        ));

        return array_values(array_unique(array_filter([...$calls[1], ...$rules[1], ...$inRules])));
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
