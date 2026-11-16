<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\File;
use Modules\MeiliFacets\Console\CheckAssetsCommand;
use Modules\MeiliFacets\Support\PublishedAssets;
use Tests\TestCase;

trait CheckAssetsCommandSteps
{
    private const string COMMAND = 'meilifacets:check-assets';

    private const string UP_TO_DATE = 'Published assets are up to date.';

    private const string SOURCE = 'resources/assets';

    private const string PUBLISHED = 'modules/meilifacets';

    private const string ASSET = 'css/meilifacets.css';

    private const int A_MINUTE = 60;

    private string $hostPublicPath;

    private string $publicPath;

    private function publishEveryAsset(): void
    {
        File::copyDirectory(module_path('MeiliFacets', self::SOURCE), $this->publicPath.'/'.self::PUBLISHED);
    }
}

// Publishes into a public directory of its own, so the host's copies decide nothing and are never touched.
uses(TestCase::class, CheckAssetsCommandSteps::class);

beforeEach(function (): void {
    $kernel = $this->app->make(Kernel::class);
    expect($kernel)->toBeInstanceOf(ConsoleKernel::class);

    // Each test ends with `Artisan::forgetBootstrappers()`; the shared application registers commands once.
    $kernel->registerCommand($this->app->make(CheckAssetsCommand::class));

    $this->hostPublicPath = $this->app->publicPath();
    $this->publicPath = sys_get_temp_dir().'/meilifacets-public-'.bin2hex(random_bytes(4));
    $this->app->usePublicPath($this->publicPath);
});

afterEach(function (): void {
    $this->app->usePublicPath($this->hostPublicPath);
    File::deleteDirectory($this->publicPath);
});

it('passes once every asset is published', function (): void {
    $this->publishEveryAsset();

    $this->artisan(self::COMMAND)
        ->expectsOutput(self::UP_TO_DATE)
        ->assertSuccessful();
});

it('fails naming the copy left behind and how to refresh it', function (): void {
    $this->publishEveryAsset();
    $published = $this->publicPath.'/'.self::PUBLISHED.'/'.self::ASSET;
    touch($published, (int) filemtime(module_path('MeiliFacets', self::SOURCE.'/'.self::ASSET)) - self::A_MINUTE);
    clearstatcache(true, $published);

    $this->artisan(self::COMMAND)
        ->expectsOutputToContain('"'.self::ASSET.'" is missing or older')
        ->expectsOutputToContain(PublishedAssets::COMMAND)
        ->doesntExpectOutput(self::UP_TO_DATE)
        ->assertFailed();
});

it('fails while nothing was ever published', function (): void {
    $this->artisan(self::COMMAND)
        ->expectsOutputToContain('"'.self::ASSET.'" is missing or older')
        ->assertFailed();
});
