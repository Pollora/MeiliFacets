<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class NumberText
{
    private const int MOST_SIGNIFICANT_DIGITS = 17;

    private const int LONGEST_PLAIN_EXPONENT = 21;

    private const int SMALLEST_PLAIN_EXPONENT = -6;

    private const string SCIENTIFIC_PATTERN = '/^(\d)(?:\.(\d+))?e([+-]\d+)$/';

    private const string ZERO = '0';

    private const string MINUS = '-';

    private const string PLUS = '+';

    private const string POINT = '.';

    private const string EXPONENT = 'e';

    public static function from(float $number): string
    {
        if ($number === 0.0) {
            return self::ZERO;
        }

        if ($number < 0) {
            return self::MINUS.self::from(-$number);
        }

        [$digits, $exponent] = self::shortestDigits($number);

        return self::written($digits, $exponent + 1);
    }

    /**
     * @return array{string, int}
     */
    private static function shortestDigits(float $number): array
    {
        $precision = 0;
        $scientific = sprintf('%.0e', $number);

        while (! self::readsBackAs($scientific, $number) && $precision < self::MOST_SIGNIFICANT_DIGITS) {
            $precision++;
            $scientific = sprintf("%.{$precision}e", $number);
        }

        preg_match(self::SCIENTIFIC_PATTERN, $scientific, $parts);

        return [rtrim($parts[1].($parts[2] ?? ''), self::ZERO), (int) $parts[3]];
    }

    private static function readsBackAs(string $scientific, float $number): bool
    {
        return (float) $scientific === $number;
    }

    /** ECMAScript `Number::toString`, step 6 onwards: `$point` is where the decimal point falls in `$digits`. */
    private static function written(string $digits, int $point): string
    {
        $length = strlen($digits);
        $isWhole = $length <= $point && $point <= self::LONGEST_PLAIN_EXPONENT;
        $isDecimal = $point > 0 && $point <= self::LONGEST_PLAIN_EXPONENT;
        $isSmallFraction = $point > self::SMALLEST_PLAIN_EXPONENT && $point <= 0;

        if ($isWhole) {
            return $digits.str_repeat(self::ZERO, $point - $length);
        }

        if ($isDecimal) {
            return substr($digits, 0, $point).self::POINT.substr($digits, $point);
        }

        if ($isSmallFraction) {
            return self::ZERO.self::POINT.str_repeat(self::ZERO, -$point).$digits;
        }

        return self::scientific($digits, $point - 1);
    }

    private static function scientific(string $digits, int $exponent): string
    {
        $mantissa = strlen($digits) === 1 ? $digits : $digits[0].self::POINT.substr($digits, 1);
        $sign = $exponent < 0 ? self::MINUS : self::PLUS;

        return $mantissa.self::EXPONENT.$sign.abs($exponent);
    }
}
