<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\NumberText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Every expected text is what `String(number)` gives in JavaScript. */
final class NumberTextTest extends TestCase
{
    /**
     * @return iterable<string, array{float, string}>
     */
    public static function numbers(): iterable
    {
        yield 'zero' => [0.0, '0'];
        yield 'negative zero' => [-0.0, '0'];
        yield 'whole number' => [15.0, '15'];
        yield 'whole number ending in zeros' => [100.0, '100'];
        yield 'negative decimal' => [-1.5, '-1.5'];
        yield 'decimal' => [12.5, '12.5'];
        yield 'tenth' => [0.1, '0.1'];
        yield 'binary rounding left visible' => [0.1 + 0.2, '0.30000000000000004'];
        yield 'beyond the exact integers' => [9007199254740993.0, '9007199254740992'];
        yield 'largest number written plainly' => [1e20, '100000000000000000000'];
        yield 'seventeen digits padded with zeros' => [123456789012345680000.0, '123456789012345680000'];
        yield 'smallest number written in exponent form' => [1e21, '1e+21'];
        yield 'large number with a mantissa' => [1.23e25, '1.23e+25'];
        yield 'largest float' => [1.7976931348623157e308, '1.7976931348623157e+308'];
        yield 'smallest fraction written plainly' => [0.000001, '0.000001'];
        yield 'small fraction with several digits' => [0.00001234, '0.00001234'];
        yield 'largest fraction written in exponent form' => [1e-7, '1e-7'];
        yield 'small fraction with a mantissa' => [1.5e-7, '1.5e-7'];
        yield 'smallest float' => [5e-324, '5e-324'];
    }

    #[DataProvider('numbers')]
    #[Test]
    public function it_writes_a_number_as_javascript_does(float $number, string $text): void
    {
        $this->assertSame($text, NumberText::from($number));
    }
}
