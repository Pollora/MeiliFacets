<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;
use LogicException;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Support\NameRefused;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchScopedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\ThrowingExceptionHandler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The host's exception handler is put back after each test. */
final class ListingRegistryReportingTest extends TestCase
{
    private ExceptionHandler $hostHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostHandler = $this->app->make(ExceptionHandler::class);
    }

    protected function tearDown(): void
    {
        $this->app->instance(ExceptionHandler::class, $this->hostHandler);

        parent::tearDown();
    }

    #[Test]
    public function it_reports_a_declared_listing_whose_binding_throws(): void
    {
        Exceptions::fake();

        $this->assertSame([], $this->registryWithABrokenBinding()->names());

        Exceptions::assertReported(LogicException::class);
    }

    #[Test]
    public function it_leaves_the_listing_out_when_the_handler_throws_too(): void
    {
        $this->app->instance(ExceptionHandler::class, new ThrowingExceptionHandler);

        $this->assertSame([], $this->registryWithABrokenBinding()->names());
    }

    #[Test]
    public function it_reports_a_second_listing_declared_under_a_taken_name_and_keeps_the_first(): void
    {
        Exceptions::fake();
        $first = FakeListing::named('products');
        $container = new Container;
        $container->instance(FakeListing::class, $first);
        $container->bind(FakeSearchScopedListing::class, static fn (): FakeSearchScopedListing => new FakeSearchScopedListing(FakeListing::named('products'), new SearchScope([])));
        $registry = new ListingRegistry($container);
        $registry->addDeclaration(FakeListing::class);
        $registry->addDeclaration(FakeSearchScopedListing::class);

        $this->assertSame($first, $registry->onlyOne());
        Exceptions::assertReported(NameRefused::class);
    }

    private function registryWithABrokenBinding(): ListingRegistry
    {
        $container = new Container;
        $container->bind(FakeListing::class, static fn (): never => throw new LogicException('Broken binding.'));
        $registry = new ListingRegistry($container);
        $registry->addDeclaration(FakeListing::class);

        return $registry;
    }
}
