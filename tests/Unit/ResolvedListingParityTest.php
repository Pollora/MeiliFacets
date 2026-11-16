<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Search\ResolvedListing;
use ReflectionClass;

/**
 * @param  class-string  $class
 * @return list<string>
 */
function resolvedListingParityMethodsOf(string $class): array
{
    return array_map(
        static fn (object $method): string => $method->getName(),
        new ReflectionClass($class)->getMethods(\ReflectionMethod::IS_PUBLIC)
    );
}

/*
 * Views read the resolved listing, never the listing itself, and it copies the
 * contract by hand: a method added to one and not the other takes the site down.
 */
it('answers everything the contract declares with the resolved listing', function (): void {
    $missing = array_diff(resolvedListingParityMethodsOf(Listing::class), resolvedListingParityMethodsOf(ResolvedListing::class));

    $this->assertSame([], array_values($missing), 'ResolvedListing does not answer what a view may ask of a listing.');
});
