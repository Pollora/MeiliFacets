<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Modules\MeiliFacets\Console\CheckParametersCommand;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;

final class CheckParametersCommandTest extends TestCase
{
    /** @var array<string, string> */
    private array $urlParameters = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->urlParameters = config('meilifacets.url_parameters', []);

        $kernel = $this->app->make(Kernel::class);
        $this->assertInstanceOf(ConsoleKernel::class, $kernel);

        // Each test ends with `Artisan::forgetBootstrappers()`; the shared application registers commands once.
        $kernel->registerCommand($this->app->make(CheckParametersCommand::class));
    }

    /** The suite shares one application: a configuration changed here would reach a later class. */
    protected function tearDown(): void
    {
        config(['meilifacets.url_parameters' => $this->urlParameters]);

        parent::tearDown();
    }

    #[Test]
    public function it_passes_on_the_names_the_project_configured(): void
    {
        $this->artisan('meilifacets:check-parameters')->assertSuccessful();
    }

    #[Test]
    public function it_refuses_a_name_a_plugin_declares_only_through_the_filter(): void
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('`checkout-link` is declared by WooCommerce.');
        }

        config(['meilifacets.url_parameters' => [...$this->urlParameters, 'product_brand' => 'checkout-link']]);

        $this->artisan('meilifacets:check-parameters')->assertFailed();
    }
}
