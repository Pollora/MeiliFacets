<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\ProbeListingBrick;
use Tests\TestCase;

trait EnclosingListingSupport
{
    private ListingRegistry $hostRegistry;
}

/** Two listings are declared for each test, whatever the host declares; its own registry is put back after. */
uses(TestCase::class, EnclosingListingSupport::class);

beforeEach(function (): void {
    $this->hostRegistry = $this->app->make(ListingRegistry::class);
    $registry = new ListingRegistry($this->app);
    $registry->add(FakeListing::named('first'));
    $registry->add(FakeListing::named('second'));
    $this->app->instance(ListingRegistry::class, $registry);
    $this->app->forgetScopedInstances();

    Blade::component(ProbeListingBrick::class, ProbeListingBrick::ALIAS);
    ProbeListingBrick::$resolved = [];
});

afterEach(function (): void {
    $this->app->instance(ListingRegistry::class, $this->hostRegistry);
    $this->app->forgetScopedInstances();
});

test('a brick inside a named root belongs to its listing', function (): void {
    Blade::render('<x-meilifacets::listing name="second"><x-probe-listing-brick /></x-meilifacets::listing>');

    expect(ProbeListingBrick::$resolved)->toBe(['second']);
});

test('a brick nested in another brick still belongs to the root', function (): void {
    Blade::render(
        '<x-meilifacets::listing name="second"><x-meilifacets::listing.drawer><x-probe-listing-brick />'
        .'</x-meilifacets::listing.drawer></x-meilifacets::listing>'
    );

    expect(ProbeListingBrick::$resolved)->toBe(['second']);
});

test('a theme component holding a listing of its own hides nothing', function (): void {
    $components = sys_get_temp_dir().'/meilifacets-wrapper-'.bin2hex(random_bytes(4));
    mkdir($components);
    file_put_contents($components.'/wrapper.blade.php', '<div>{{ $slot }}</div>');
    Blade::anonymousComponentPath($components, 'probe');

    try {
        Blade::render(
            '<x-meilifacets::listing name="second"><x-probe::wrapper listing="first"><x-probe-listing-brick />'
            .'</x-probe::wrapper></x-meilifacets::listing>'
        );
    } finally {
        File::delete($components.'/wrapper.blade.php');
        rmdir($components);
    }

    expect(ProbeListingBrick::$resolved)->toBe(['second']);
});

test('a named brick keeps its own listing inside another root', function (): void {
    Blade::render(
        '<x-meilifacets::listing name="second"><x-probe-listing-brick name="first" /></x-meilifacets::listing>'
    );

    expect(ProbeListingBrick::$resolved)->toBe(['first']);
});

test('a brick after a root belongs to no listing of its own', function (): void {
    $this->expectException(ViewException::class);
    $this->expectExceptionMessage('Name the listing: 2 are declared (first, second).');

    Blade::render('<x-meilifacets::listing name="second"></x-meilifacets::listing><x-probe-listing-brick />');
});
