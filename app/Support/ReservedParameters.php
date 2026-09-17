<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

use Modules\MeiliFacets\Enums\QueryParameter;

/** Names a listing parameter must never take. */
final readonly class ReservedParameters
{
    /**
     * Varnish drops the whole query string when the first parameter is one of
     * these (clevercloud/_varnish.vcl).
     */
    private const array STRIPPED_BY_VARNISH = [
        'utm_campaign', 'utm_medium', 'utm_source', 'utm_term',
        'adParams', 'client', 'cx', 'eid', 'fbid', 'feed',
        'ref', 'refid', 'refsrc', 'ver', 'view',
    ];

    /**
     * Names a plugin reads straight from `$_GET`, so they never reach
     * `public_query_vars` and `wordPress()` cannot see them. WooCommerce's list is
     * at `class-wc-query.php:316-318`. `NativeFiltering` stops it filtering the main
     * query, but not seeing the names: `wc_is_filtered()`
     * (`wc-conditional-functions.php:341`) and its widgets still act on them.
     */
    private const array READ_FROM_GET = [
        'min_price', 'max_price', 'rating_filter', 'orderby',
    ];

    /** The same source treats anything starting with this as one of its own. */
    private const string FILTER_PREFIX = 'filter_';

    /**
     * @return list<string>
     */
    public function wordPress(): array
    {
        global $wp;

        return array_values($wp->public_query_vars ?? []);
    }

    /**
     * @return list<string>
     */
    public function proxy(): array
    {
        return self::STRIPPED_BY_VARNISH;
    }

    /**
     * @return list<string>
     */
    public function plugins(): array
    {
        return self::READ_FROM_GET;
    }

    /**
     * Collisions taken on purpose (`D-h`), and only while the bound answers to the name.
     *
     * @return list<string>
     */
    public function acceptedFor(UrlParameters $parameters): array
    {
        $accepted = [];

        foreach ([QueryParameter::MinPrice, QueryParameter::MaxPrice] as $bound) {
            if ($parameters->reserved($bound) === $bound->value) {
                $accepted[] = $bound->value;
            }
        }

        return $accepted;
    }

    public function reason(string $parameter): ?string
    {
        if (in_array($parameter, $this->wordPress(), true)) {
            return 'a public WordPress query var: WordPress would filter its own query in parallel';
        }

        if (in_array($parameter, self::READ_FROM_GET, true) || str_starts_with($parameter, self::FILTER_PREFIX)) {
            return 'read from $_GET by WooCommerce: its widgets and wc_is_filtered() act on it';
        }

        if (in_array($parameter, $this->proxy(), true)) {
            return 'stripped by Varnish when it comes first: the whole query string would be dropped';
        }

        return null;
    }

    /**
     * @param  list<string>  $parameters
     * @return array<string, string> offending parameter to reason
     */
    public function conflicts(array $parameters): array
    {
        $conflicts = [];

        foreach ($parameters as $parameter) {
            $reason = $this->reason($parameter);

            if ($reason !== null) {
                $conflicts[$parameter] = $reason;
            }
        }

        return $conflicts;
    }
}
