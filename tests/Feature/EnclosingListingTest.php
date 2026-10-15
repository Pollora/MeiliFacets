<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\ProbeListingBrick;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Two listings are declared for each test, whatever the host declares; its own registry is put back after. */
final class EnclosingListingTest extends TestCase
{
    private ListingRegistry $hostRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostRegistry = $this->app->make(ListingRegistry::class);
        $registry = new ListingRegistry($this->app);
        $registry->add(FakeListing::named('first'));
        $registry->add(FakeListing::named('second'));
        $this->app->instance(ListingRegistry::class, $registry);
        $this->app->forgetScopedInstances();

        Blade::component(ProbeListingBrick::class, ProbeListingBrick::ALIAS);
        ProbeListingBrick::$resolved = [];
    }

    protected function tearDown(): void
    {
        $this->app->instance(ListingRegistry::class, $this->hostRegistry);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function a_brick_inside_a_named_root_belongs_to_its_listing(): void
    {
        Blade::render('<x-meilifacets::listing name="second"><x-probe-listing-brick /></x-meilifacets::listing>');

        $this->assertSame(['second'], ProbeListingBrick::$resolved);
    }

    #[Test]
    public function a_brick_nested_in_another_brick_still_belongs_to_the_root(): void
    {
        Blade::render(
            '<x-meilifacets::listing name="second"><x-meilifacets::listing.drawer><x-probe-listing-brick />'
            .'</x-meilifacets::listing.drawer></x-meilifacets::listing>'
        );

        $this->assertSame(['second'], ProbeListingBrick::$resolved);
    }

    #[Test]
    public function a_named_brick_keeps_its_own_listing_inside_another_root(): void
    {
        Blade::render(
            '<x-meilifacets::listing name="second"><x-probe-listing-brick name="first" /></x-meilifacets::listing>'
        );

        $this->assertSame(['first'], ProbeListingBrick::$resolved);
    }

    #[Test]
    public function a_brick_after_a_root_belongs_to_no_listing_of_its_own(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Name the listing: 2 are declared (first, second).');

        Blade::render('<x-meilifacets::listing name="second"></x-meilifacets::listing><x-probe-listing-brick />');
    }
}
