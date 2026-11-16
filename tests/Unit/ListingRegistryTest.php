<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Listing\ListingUnavailable;
use Modules\MeiliFacets\Support\NameRefused;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;

it('hands back the only listing declared', function (): void {
    $registry = new ListingRegistry(new Container);
    $registry->add(FakeListing::named('products'));

    expect($registry->onlyOne()->name())->toBe('products');
});

it('refuses to guess when nothing is declared', function (): void {
    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('none is declared');

    new ListingRegistry(new Container)->onlyOne();
});

// A second listing changes what the first one shows: the template has to say which.
it('names the candidates when several are declared', function (): void {
    $registry = new ListingRegistry(new Container);
    $registry->add(FakeListing::named('products'));
    $registry->add(FakeListing::named('articles'));

    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('2 are declared (products, articles)');

    $registry->onlyOne();
});

// A second listing under the same name would silently replace the catalogue a template shows.
it('keeps the first listing declared under a name', function (): void {
    $registry = new ListingRegistry(new Container);
    $first = FakeListing::named('products');
    $registry->add($first);

    try {
        $registry->add(FakeListing::named('products'));
        $this->fail('A second listing named "products" was accepted.');
    } catch (NameRefused $refused) {
        expect($refused->getMessage())->toContain('"products" is already declared');
    }

    expect($registry->named('products'))->toBe($first);
});

it('leaves out a listing whose dependency is not loaded', function (): void {
    $container = new Container;
    $container->bind(FakeListing::class, fn (): never => throw new ListingUnavailable('WooCommerce is not loaded yet.'));
    $registry = new ListingRegistry($container);
    $registry->addDeclaration(FakeListing::class);

    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('none is declared');

    $registry->onlyOne();
});

// Pollora may apply a module's discovery before WordPress loads its plugins.
it('builds a declared listing once on first lookup', function (): void {
    $builds = 0;
    $container = new Container;
    $container->bind(FakeListing::class, static function () use (&$builds): FakeListing {
        $builds++;

        return FakeListing::named('products');
    });
    $registry = new ListingRegistry($container);

    $registry->addDeclaration(FakeListing::class);
    expect($builds)->toBe(0);

    expect($registry->onlyOne()->name())->toBe('products');
    expect($registry->names())->toBe(['products']);
    expect($builds)->toBe(1);
});
