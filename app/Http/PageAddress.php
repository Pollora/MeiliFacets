<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Modules\MeiliFacets\Support\ReservedParameters;

/** The address of the page a listing sits on: its path and the query parameters WordPress reads, less the page number and the listing's own. */
final readonly class PageAddress
{
    /** The listing reads it when its own page parameter is absent: kept, it would turn the page back. */
    public const string PAGED_QUERY_VAR = 'paged';

    public function __construct(private ReservedParameters $reserved) {}

    public function path(): string
    {
        return wp_parse_url(get_pagenum_link(1, false), PHP_URL_PATH) ?: '/';
    }

    /**
     * Read from the raw query string: Laravel's middlewares trim values and turn empty ones into null, and `?s=` is still a search to WordPress.
     *
     * @param  list<string>  $names  names the listing handles itself
     */
    public function queryWithout(array $names): string
    {
        $read = array_diff($this->reserved->wordPress(), [...$names, self::PAGED_QUERY_VAR]);

        return implode('&', array_filter($this->pairs(), fn (string $pair): bool => in_array($this->nameOf($pair), $read, true)));
    }

    /**
     * @return list<string>
     */
    private function pairs(): array
    {
        $separators = preg_quote((string) ini_get('arg_separator.input'), '/');

        return preg_split("/[{$separators}]/", (string) request()->server('QUERY_STRING')) ?: [];
    }

    /** PHP's own reading of the name: `post.type` and `post_type[]` both reach WordPress as `post_type`. */
    private function nameOf(string $pair): int|string|null
    {
        parse_str($pair, $parsed);

        return array_key_first($parsed);
    }
}
