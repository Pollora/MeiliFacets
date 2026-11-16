<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Collator;
use Modules\MeiliFacets\Console\CheckAssetsCommand;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use Modules\MeiliFacets\Support\PublishedAssets;
use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\SiteCollator;
use Modules\MeiliFacets\Support\SiteLocale;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\Application;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** Reads WordPress and Laravel helpers, so each test gets a process of its own: see `Doubles\WordPress`. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SupportRuntimeTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        WordPress::boot();
        $this->directory = sys_get_temp_dir().'/meilifacets-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/module/resources/assets/css', recursive: true);
        mkdir($this->directory.'/public/modules/meilifacets/css', recursive: true);

        WordPress::fake('module_path', fn (string $name, string $path): string => $this->directory.'/module/'.$path);
        WordPress::fake('public_path', fn (string $path): string => $this->directory.'/public/'.$path);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/{module,public/modules/meilifacets}/{resources/assets/,}css/*', GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }

        foreach (['/module/resources/assets/css', '/module/resources/assets', '/module/resources', '/module', '/public/modules/meilifacets/css', '/public/modules/meilifacets', '/public/modules', '/public', ''] as $folder) {
            @rmdir($this->directory.$folder);
        }
    }

    #[Test]
    public function it_reads_the_site_locale_from_wordpress_once_it_is_loaded(): void
    {
        WordPress::fake('get_locale', 'fr_FR');

        $this->assertSame('fr_FR', SiteLocale::current());
    }

    #[Test]
    public function it_falls_back_to_the_application_locale_when_wordpress_gives_none(): void
    {
        WordPress::fake('get_locale', '');
        $app = app();
        $this->assertInstanceOf(Application::class, $app);
        $app->locale = 'de';

        $this->assertSame('de', SiteLocale::current());
    }

    #[Test]
    public function it_builds_one_collator_per_locale_and_orders_numbers_by_value(): void
    {
        WordPress::fake('get_locale', 'fr_FR');
        $collators = new SiteCollator;

        $collator = $collators->current();

        $this->assertInstanceOf(Collator::class, $collator);
        $this->assertSame($collator, $collators->current());
        $this->assertLessThan(0, $collator->compare('2 ml', '10 ml'));
    }

    #[Test]
    public function it_has_no_collator_for_a_locale_icu_refuses(): void
    {
        WordPress::fake('get_locale', str_repeat('x', 300));

        $this->assertNull(new SiteCollator()->current());
    }

    #[Test]
    public function it_lists_the_assets_missing_or_older_than_the_modules_own(): void
    {
        file_put_contents($this->directory.'/module/resources/assets/css/new.css', 'a');
        file_put_contents($this->directory.'/module/resources/assets/css/old.css', 'a');
        file_put_contents($this->directory.'/module/resources/assets/css/fresh.css', 'a');
        file_put_contents($this->directory.'/public/modules/meilifacets/css/old.css', 'a');
        file_put_contents($this->directory.'/public/modules/meilifacets/css/fresh.css', 'a');
        touch($this->directory.'/module/resources/assets/css/old.css', time());
        touch($this->directory.'/public/modules/meilifacets/css/old.css', time() - 100);

        $stale = new PublishedAssets()->stale();
        sort($stale);

        $this->assertSame(['css/new.css', 'css/old.css'], $stale);
    }

    #[Test]
    public function it_has_nothing_to_publish_without_a_source_folder(): void
    {
        WordPress::fake('module_path', $this->directory.'/absent');

        $this->assertSame([], new PublishedAssets()->stale());
    }

    #[Test]
    public function the_assets_check_passes_when_nothing_is_stale(): void
    {
        [$status, $output] = $this->execute(new CheckAssetsCommand);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Published assets are up to date.', $output);
    }

    #[Test]
    public function the_assets_check_names_each_stale_file_and_the_command_that_fixes_it(): void
    {
        file_put_contents($this->directory.'/module/resources/assets/css/new.css', 'a');

        [$status, $output] = $this->execute(new CheckAssetsCommand);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('"css/new.css" is missing or older', $output);
        $this->assertStringContainsString(PublishedAssets::COMMAND, $output);
    }

    #[Test]
    public function the_parameters_check_fails_when_permalinks_are_off(): void
    {
        $this->permalinks(false);

        [$status, $output] = $this->execute(new CheckParametersCommand);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Permalinks are off', $output);
    }

    #[Test]
    public function the_parameters_check_fails_on_a_blocking_collision(): void
    {
        $this->permalinks(true);
        $this->container()->instance(UrlParameters::class, new UrlParameters(['product_brand' => 'orderby']));

        [$status, $output] = $this->execute(new CheckParametersCommand);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('"orderby" is read from $_GET by WooCommerce', $output);
        $this->assertStringContainsString('Rename them in config/meilifacets.php', $output);
    }

    #[Test]
    public function the_parameters_check_warns_about_the_price_bounds_taken_on_purpose(): void
    {
        $this->permalinks(true);

        [$status, $output] = $this->execute(new CheckParametersCommand);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('"min_price" is read from $_GET', $output);
        $this->assertStringContainsString('Taken on purpose', $output);
        $this->assertStringContainsString('No blocking conflict. 5 parameters checked.', $output);
    }

    #[Test]
    public function the_parameters_check_is_silent_about_renamed_price_bounds(): void
    {
        $this->permalinks(true);
        $this->container()->instance(UrlParameters::class, new UrlParameters([], ['min_price' => 'from', 'max_price' => 'to']));

        [$status, $output] = $this->execute(new CheckParametersCommand);

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString('Taken on purpose', $output);
    }

    #[Test]
    public function it_reads_the_declared_query_vars_before_wordpress_initialised(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s', 'p']];

        $this->assertSame(['s', 'p'], new ReservedParameters()->wordPress());
    }

    #[Test]
    public function it_applies_the_query_vars_filter_once_initialised_and_remembers_the_answer(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s']];
        WordPress::fake('did_action', static fn (string $hook): int => $hook === 'init' ? 1 : 0);
        WordPress::fake('apply_filters', static fn (string $hook, array $vars): array => [...$vars, 's', 'product_cat']);
        $reserved = new ReservedParameters;

        $this->assertSame(['s', 'product_cat'], $reserved->wordPress());
        $this->assertSame(['s', 'product_cat'], $reserved->wordPress());
        $this->assertSame(1, WordPress::$calls['apply_filters']);
        $this->assertStringContainsString('public WordPress query var', (string) $reserved->reason('product_cat'));
    }

    #[Test]
    public function it_does_not_filter_the_query_vars_again_once_the_request_was_parsed(): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s']];
        WordPress::fake('did_action', 1);
        WordPress::fake('apply_filters', static fn (): array => ['never']);

        $this->assertSame(['s'], new ReservedParameters()->wordPress());
    }

    /**
     * @return array{int, string}
     */
    private function execute(CheckAssetsCommand|CheckParametersCommand $command): array
    {
        $container = $this->container();
        $command->setLaravel($container);
        $output = new BufferedOutput;

        $status = $command->run(new ArrayInput([]), $output);

        return [$status, $output->fetch()];
    }

    private function container(): Application
    {
        $container = app();
        $this->assertInstanceOf(Application::class, $container);

        return $container;
    }

    private function permalinks(bool $on): void
    {
        $GLOBALS['wp'] = (object) ['public_query_vars' => ['s']];
        $GLOBALS['wp_rewrite'] = new class($on)
        {
            public function __construct(private readonly bool $on) {}

            public function using_permalinks(): bool
            {
                return $this->on;
            }
        };
    }
}
