<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\SortDirection;

it('writes a meilisearch sort expression for a field', function (): void {
    expect(SortDirection::Ascending->of('post_date'))->toBe('post_date:asc');
    expect(SortDirection::Descending->of('price.max'))->toBe('price.max:desc');
});

it('reads the field back out of an expression', function (): void {
    expect(SortDirection::fieldOf('price.min:asc'))->toBe('price.min');
});

it('reads an expression with no direction as the field itself', function (): void {
    expect(SortDirection::fieldOf('post_title'))->toBe('post_title');
});
