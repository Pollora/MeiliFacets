<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ListingRegistryTest extends TestCase
{
    #[Test]
    public function it_hands_back_the_only_listing_declared(): void
    {
        $registry = new ListingRegistry;
        $registry->add(FakeListing::named('products'));

        $this->assertSame('products', $registry->sole()->name());
    }

    #[Test]
    public function it_refuses_to_guess_when_nothing_is_declared(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('none is declared');

        (new ListingRegistry)->sole();
    }

    /** A second listing changes what the first one shows: the template has to say which. */
    #[Test]
    public function it_names_the_candidates_when_several_are_declared(): void
    {
        $registry = new ListingRegistry;
        $registry->add(FakeListing::named('products'));
        $registry->add(FakeListing::named('articles'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('2 are declared (products, articles)');

        $registry->sole();
    }
}
