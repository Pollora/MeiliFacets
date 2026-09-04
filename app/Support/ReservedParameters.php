<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

/**
 * Names a listing parameter must never take. Checked by the console command,
 * never on a request: the answer only changes when configuration does.
 */
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

    public function reason(string $parameter): ?string
    {
        if (in_array($parameter, $this->wordPress(), true)) {
            return 'a public WordPress query var: WordPress would filter its own query in parallel';
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
