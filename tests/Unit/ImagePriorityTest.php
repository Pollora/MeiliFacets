<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ImagePriority;

use Modules\MeiliFacets\Enums\ImagePriority;

it('loads the cards above the fold eagerly', function (): void {
    expect(ImagePriority::forRank(0, 4))->toBe(ImagePriority::Eager);
    expect(ImagePriority::forRank(3, 4))->toBe(ImagePriority::Eager);
    expect(ImagePriority::forRank(4, 4))->toBe(ImagePriority::Lazy);
});

it('never hints a priority it does not mean', function (): void {
    expect(ImagePriority::Eager->loading())->toBe('eager');
    expect(ImagePriority::Eager->fetchPriority())->toBe('high');
    expect(ImagePriority::Lazy->loading())->toBe('lazy');
    expect(ImagePriority::Lazy->fetchPriority())->toBe('auto');
});
