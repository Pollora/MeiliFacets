<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Generator;
use InvalidArgumentException;
use Modules\MeiliFacets\Listing\CardVariant;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Listing\VariantChoice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The server half of `tests/card-variant-cases.json`; `tests/ts/variant-choice.test.ts`
 * applies the same cases in the browser.
 *
 * @phpstan-type VariantCase array{
 *     case: string,
 *     card: array<string, mixed>,
 *     selected: array<string, list<string>>,
 *     price: array{min: float|int|null, max: float|int|null},
 *     expected: array<string, mixed>,
 *     sortFilter?: array{field: string, value: string}
 * }
 */
final class VariantChoiceTest extends TestCase
{
    private const string CASES = __DIR__.'/../card-variant-cases.json';

    /**
     * @return Generator<string, array{VariantCase, list<string>}>
     */
    public static function cases(): Generator
    {
        /** @var array{variantTaxonomies: list<string>, cases: list<VariantCase>} $cases */
        $cases = json_decode((string) file_get_contents(self::CASES), true, flags: JSON_THROW_ON_ERROR);

        foreach ($cases['cases'] as $case) {
            yield $case['case'] => [$case, $cases['variantTaxonomies']];
        }
    }

    /**
     * @param  VariantCase  $case
     * @param  list<string>  $variantTaxonomies
     */
    #[DataProvider('cases')]
    #[Test]
    public function it_shows_the_card_through_the_matching_variant_or_as_projected(array $case, array $variantTaxonomies): void
    {
        $price = new Range($this->bound($case['price']['min']), $this->bound($case['price']['max']));
        $sortFilter = isset($case['sortFilter']) ? new SortFilter($case['sortFilter']['field'], $case['sortFilter']['value']) : null;
        $choice = new VariantChoice($case['selected'], $price, $variantTaxonomies, $sortFilter);

        $this->assertSame($case['expected'], $choice->shown($case['card']));
    }

    #[Test]
    public function a_stored_variant_reads_back_as_itself(): void
    {
        $variant = new CardVariant(['pa_volume' => ['400ml']], 39.0, ['volume' => '400ml']);

        $this->assertSame($variant->toArray(), CardVariant::read($variant->toArray())?->toArray());
    }

    private function bound(float|int|null $bound): ?float
    {
        return $bound === null ? null : (float) $bound;
    }

    #[Test]
    public function a_variant_refuses_a_price_the_index_cannot_hold(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CardVariant(['pa_volume' => ['400ml']], INF);
    }
}
