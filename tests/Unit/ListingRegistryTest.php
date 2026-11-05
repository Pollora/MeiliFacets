<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Listing\ListingUnavailable;
use Modules\MeiliFacets\Support\NameRefused;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        $this->expectException(NameRefused::class);
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

        $this->expectException(NameRefused::class);
        $this->expectExceptionMessage('2 are declared (products, articles)');

        $registry->onlyOne();
    }

    /** A second listing under the same name would silently replace the catalogue a template shows. */
    #[Test]
    public function it_keeps_the_first_listing_declared_under_a_name(): void
    {
        $registry = new ListingRegistry(new Container);
        $first = FakeListing::named('products');
        $registry->add($first);

        try {
            $registry->add(FakeListing::named('products'));
            $this->fail('A second listing named "products" was accepted.');
        } catch (NameRefused $refused) {
            $this->assertStringContainsString('"products" is already declared', $refused->getMessage());
        }

        $this->assertSame($first, $registry->named('products'));
    }

    #[Test]
    public function it_leaves_out_a_listing_whose_dependency_is_not_loaded(): void
    {
        $container = new Container;
        $container->bind(FakeListing::class, $this->unavailable(...));
        $registry = new ListingRegistry($container);
        $registry->addDeclaration(FakeListing::class);

        $this->expectException(NameRefused::class);
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
