<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\Range;

final readonly class FilterExpression
{
    // One pass over both characters: escaping them in sequence would let a value
    // ending in a backslash close the string.
    private const string ESCAPED = '/[\\\\"]/';

    /**
     * @param  list<string>  $clauses
     */
    public static function all(array $clauses): string
    {
        return implode(' AND ', array_filter($clauses, strlen(...)));
    }

    /**
     * @param  list<string>  $values
     */
    public static function facet(Facet $facet, array $values): string
    {
        if ($values === []) {
            return '';
        }

        $clauses = array_map(
            static fn (string $value): string => $facet->field().' = '.self::quote($value),
            $values
        );

        return count($clauses) > 1 ? '('.implode(' OR ', $clauses).')' : $clauses[0];
    }

    /**
     * `field != value` excludes every document missing the field entirely, so a
     * negative filter has to be written as a negated equality.
     */
    public static function without(string $field, string $value): string
    {
        return 'NOT '.$field.' = '.self::quote($value);
    }

    /**
     * Two intervals overlap unless one ends before the other starts — the test
     * WooCommerce writes in SQL, which is what makes a product sold from 28 to 62
     * answer a search for 40 to 70.
     */
    public static function overlapping(Range $range): string
    {
        return self::all(array_values(array_filter([
            $range->max === null ? '' : PriceField::Min->path().' <= '.self::number($range->max),
            $range->min === null ? '' : PriceField::Max->path().' >= '.self::number($range->min),
        ])));
    }

    public static function equals(string $field, string $value): string
    {
        return $field.' = '.self::quote($value);
    }

    /**
     * A bound is a number, never a quoted string: Meilisearch compares them
     * differently. Fixed notation to four decimals, because `(string) 1.0E-9` is
     * not a filter and no currency carries more than three.
     */
    private static function number(float $bound): string
    {
        return rtrim(rtrim(sprintf('%.4F', $bound), '0'), '.');
    }

    private static function quote(string $value): string
    {
        return '"'.preg_replace(self::ESCAPED, '\\\\$0', $value).'"';
    }
}
