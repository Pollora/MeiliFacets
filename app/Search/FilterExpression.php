<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Listing\Facet;

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

    public static function equals(string $field, string $value): string
    {
        return $field.' = '.self::quote($value);
    }

    private static function quote(string $value): string
    {
        return '"'.preg_replace(self::ESCAPED, '\\\\$0', $value).'"';
    }
}
