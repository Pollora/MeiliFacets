<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use Tests\TestCase;
use WooCommerce;

trait CheckParametersCommandSteps
{
    /** @var array<string, string> */
    private array $urlParameters = [];
}

uses(TestCase::class, CheckParametersCommandSteps::class);

beforeEach(function (): void {
    $this->urlParameters = config('meilifacets.url_parameters', []);

    $kernel = $this->app->make(Kernel::class);
    expect($kernel)->toBeInstanceOf(ConsoleKernel::class);

    // Each test ends with `Artisan::forgetBootstrappers()`; the shared application registers commands once.
    $kernel->registerCommand($this->app->make(CheckParametersCommand::class));
});

// The suite shares one application: a configuration changed here would reach a later class.
afterEach(function (): void {
    config(['meilifacets.url_parameters' => $this->urlParameters]);
});

it('passes on the names the project configured', function (): void {
    $this->artisan('meilifacets:check-parameters')->assertSuccessful();
});

// Set in memory: `set_permalink_structure()` would save the option, and the host project's site with it.
it('refuses a site without permalinks', function (): void {
    global $wp_rewrite;

    $structure = $wp_rewrite->permalink_structure;
    $wp_rewrite->permalink_structure = '';

    try {
        $this->artisan('meilifacets:check-parameters')
            ->expectsOutputToContain('Permalinks are off')
            ->assertFailed();
    } finally {
        $wp_rewrite->permalink_structure = $structure;
    }
});

it('refuses a name a plugin declares only through the filter', function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('`checkout-link` is declared by WooCommerce.');
    }

    config(['meilifacets.url_parameters' => [...$this->urlParameters, 'product_brand' => 'checkout-link']]);

    $this->artisan('meilifacets:check-parameters')->assertFailed();
});
