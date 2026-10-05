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
            static fn (string $value): string => self::equals($facet->field(), $value),
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
     *
     * A bound is a number, never a quoted string: Meilisearch compares them differently.
     */
    public static function overlapping(Range $range): string
    {
        return self::all(array_values(array_filter([
            $range->max === null ? '' : PriceField::Min->path().' <= '.Range::formatBound($range->max),
            $range->min === null ? '' : PriceField::Max->path().' >= '.Range::formatBound($range->min),
        ])));
    }

    /**
     * @param  non-empty-list<int>  $values
     */
    public static function oneOf(string $field, array $values): string
    {
        return $field.' IN ['.implode(', ', $values).']';
    }

    public static function equals(string $field, string $value): string
    {
        return $field.' = '.self::quote($value);
    }

    private static function quote(string $value): string
    {
        return '"'.preg_replace(self::ESCAPED, '\\\\$0', $value).'"';
    }
}
