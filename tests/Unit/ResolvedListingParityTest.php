<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Listing\ResolvedListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Views read the resolved listing, never the listing itself, and it copies the
 * contract by hand: a method added to one and not the other takes the site down.
 */
final class ResolvedListingParityTest extends TestCase
{
    #[Test]
    public function the_resolved_listing_answers_everything_the_contract_declares(): void
    {
        $missing = array_diff($this->methodsOf(Listing::class), $this->methodsOf(ResolvedListing::class));

        $this->assertSame([], array_values($missing), 'ResolvedListing does not answer what a view may ask of a listing.');
    }

    /**
     * @param  class-string  $class
     * @return list<string>
     */
    private function methodsOf(string $class): array
    {
        return array_map(
            static fn (object $method): string => $method->getName(),
            new ReflectionClass($class)->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
    }
}
