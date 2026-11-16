<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\EngineLimits;

use Modules\MeiliFacets\Search\EngineLimits;

it('reads a distribution stopping on its own ceiling as cut', function (): void {
    expect(new EngineLimits(1000, 400)->looksTruncated(400))->toBeTrue();
});

/** Between a release and its reindexing, the index still caps where the engine does. */
it('reads one stopping on the engine default as cut too', function (): void {
    expect(new EngineLimits(1000, 400)->looksTruncated(EngineLimits::ENGINE_MAX_FACET_VALUES))->toBeTrue();
});

it('says nothing of a distribution that stops on neither', function (): void {
    expect(new EngineLimits(1000, 400)->looksTruncated(99))->toBeFalse();
    expect(new EngineLimits(1000, 400)->looksTruncated(399))->toBeFalse();
});
