<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\File;
use Modules\MeiliFacets\Console\CheckAssetsCommand;
use Modules\MeiliFacets\Support\PublishedAssets;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Publishes into a public directory of its own, so the host's copies decide nothing and are never touched. */
final class CheckAssetsCommandTest extends TestCase
{
    private const string COMMAND = 'meilifacets:check-assets';

    private const string UP_TO_DATE = 'Published assets are up to date.';

    private const string SOURCE = 'resources/assets';

    private const string PUBLISHED = 'modules/meilifacets';

    private const string ASSET = 'css/meilifacets.css';

    private const int A_MINUTE = 60;

    private string $hostPublicPath;

    private string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = $this->app->make(Kernel::class);
        $this->assertInstanceOf(ConsoleKernel::class, $kernel);

        // Each test ends with `Artisan::forgetBootstrappers()`; the shared application registers commands once.
        $kernel->registerCommand($this->app->make(CheckAssetsCommand::class));

        $this->hostPublicPath = $this->app->publicPath();
        $this->publicPath = sys_get_temp_dir().'/meilifacets-public-'.bin2hex(random_bytes(4));
        $this->app->usePublicPath($this->publicPath);
    }

    protected function tearDown(): void
    {
        $this->app->usePublicPath($this->hostPublicPath);
        File::deleteDirectory($this->publicPath);

        parent::tearDown();
    }

    #[Test]
    public function it_passes_once_every_asset_is_published(): void
    {
        $this->publishEveryAsset();

        $this->artisan(self::COMMAND)
            ->expectsOutput(self::UP_TO_DATE)
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_naming_the_copy_left_behind_and_how_to_refresh_it(): void
    {
        $this->publishEveryAsset();
        $published = $this->publicPath.'/'.self::PUBLISHED.'/'.self::ASSET;
        touch($published, (int) filemtime(module_path('MeiliFacets', self::SOURCE.'/'.self::ASSET)) - self::A_MINUTE);
        clearstatcache(true, $published);

        $this->artisan(self::COMMAND)
            ->expectsOutputToContain('"'.self::ASSET.'" is missing or older')
            ->expectsOutputToContain(PublishedAssets::COMMAND)
            ->doesntExpectOutput(self::UP_TO_DATE)
            ->assertFailed();
    }

    #[Test]
    public function it_fails_while_nothing_was_ever_published(): void
    {
        $this->artisan(self::COMMAND)
            ->expectsOutputToContain('"'.self::ASSET.'" is missing or older')
            ->assertFailed();
    }

    private function publishEveryAsset(): void
    {
        File::copyDirectory(module_path('MeiliFacets', self::SOURCE), $this->publicPath.'/'.self::PUBLISHED);
    }
}
