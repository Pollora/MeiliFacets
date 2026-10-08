<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Listing\ListingUnavailable;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ListingRegistryTest extends TestCase
{
    #[Test]
    public function it_hands_back_the_only_listing_declared(): void
    {
        $registry = new ListingRegistry(new Container);
        $registry->add(FakeListing::named('products'));

        $this->assertSame('products', $registry->onlyOne()->name());
    }

    #[Test]
    public function it_refuses_to_guess_when_nothing_is_declared(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('none is declared');

        new ListingRegistry(new Container)->onlyOne();
    }

    /** A second listing changes what the first one shows: the template has to say which. */
    #[Test]
    public function it_names_the_candidates_when_several_are_declared(): void
    {
        $registry = new ListingRegistry(new Container);
        $registry->add(FakeListing::named('products'));
        $registry->add(FakeListing::named('articles'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('2 are declared (products, articles)');

        $registry->onlyOne();
    }

    #[Test]
    public function it_leaves_out_a_listing_whose_dependency_is_not_loaded(): void
    {
        $container = new Container;
        $container->bind(FakeListing::class, $this->unavailable(...));
        $registry = new ListingRegistry($container);
        $registry->addDeclaration(FakeListing::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('none is declared');

        $registry->onlyOne();
    }

    /** Pollora may apply a module's discovery before WordPress loads its plugins. */
    #[Test]
    public function it_builds_a_declared_listing_once_on_first_lookup(): void
    {
        $builds = 0;
        $container = new Container;
        $container->bind(FakeListing::class, static function () use (&$builds): FakeListing {
            $builds++;

            return FakeListing::named('products');
        });
        $registry = new ListingRegistry($container);

        $registry->addDeclaration(FakeListing::class);
        $this->assertSame(0, $builds);

        $this->assertSame('products', $registry->onlyOne()->name());
        $this->assertSame(['products'], $registry->names());
        $this->assertSame(1, $builds);
    }

    private function unavailable(): never
    {
        throw new ListingUnavailable('WooCommerce is not loaded yet.');
    }
}
