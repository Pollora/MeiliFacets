<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\VariantChoice;

use InvalidArgumentException;
use Modules\MeiliFacets\Listing\CardVariant;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Listing\VariantChoice;

// The server half of `tests/card-variant-cases.json`; `tests/ts/variant-choice.test.ts`
// applies the same cases in the browser.
//
// @phpstan-type VariantCase array{
//     case: string,
//     card: array<string, mixed>,
//     selected: array<string, list<string>>,
//     price: array{min: float|int|null, max: float|int|null},
//     expected: array<string, mixed>,
//     sortFilter?: array{field: string, value: string}
// }

const CASES = __DIR__.'/../card-variant-cases.json';

function bound(float|int|null $bound): ?float
{
    return $bound === null ? null : (float) $bound;
}

it('shows the card through the matching variant or as projected', function (array $case, array $variantTaxonomies): void {
    $state = new ListingState($case['selected'], price: new Range(bound($case['price']['min']), bound($case['price']['max'])));
    $sortFilter = isset($case['sortFilter']) ? new SortFilter($case['sortFilter']['field'], $case['sortFilter']['value']) : null;
    $choice = new VariantChoice($state, $variantTaxonomies, $sortFilter);

    expect($choice->shown($case['card']))->toBe($case['expected']);
})->with(function () {
    /** @var array{variantTaxonomies: list<string>, cases: list<VariantCase>} $cases */
    $cases = json_decode((string) file_get_contents(CASES), true, flags: JSON_THROW_ON_ERROR);

    foreach ($cases['cases'] as $case) {
        yield $case['case'] => [$case, $cases['variantTaxonomies']];
    }
});

test('a stored variant reads back as itself', function (): void {
    $variant = new CardVariant(['pa_volume' => ['400ml']], 39.0, ['volume' => '400ml']);

    expect(CardVariant::read($variant->toArray())?->toArray())->toBe($variant->toArray());
});

test('a variant refuses a price the index cannot hold', function (): void {
    $this->expectException(InvalidArgumentException::class);

    new CardVariant(['pa_volume' => ['400ml']], INF);
});
