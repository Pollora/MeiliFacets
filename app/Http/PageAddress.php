<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\UrlParameters;

/** The address of the page a listing sits on: the path of each of its pages, and the query parameters that come with it. */
final readonly class PageAddress
{
    /** The listing reads it when its own page parameter is absent: kept, it would turn the page back. */
    public const string PAGED_QUERY_VAR = 'paged';

    public const string PAGE_PLACEHOLDER = ':page';

    /** `get_pagenum_link()` takes no placeholder: WooCommerce substitutes one the same way (`loop/pagination.php`). */
    private const int UNREACHED_PAGE = PHP_INT_MAX;

    public function __construct(private ReservedParameters $reserved, private UrlParameters $parameters) {}

    public function path(): string
    {
        return $this->pathOf(ListingState::FIRST_PAGE);
    }

    public function pathPattern(): string
    {
        return str_replace((string) self::UNREACHED_PAGE, self::PAGE_PLACEHOLDER, $this->pathOf(self::UNREACHED_PAGE));
    }

    public function pathOf(int $page): string
    {
        return wp_parse_url(get_pagenum_link($page, false), PHP_URL_PATH) ?: '/';
    }

    /** Only the listing's parameters and those WordPress reads travel: `add-to-cart` would be repeated on every page. */
    public function linkedQuery(): string
    {
        $query = implode('&', $this->pairsWhere($this->travels(...)));

        return $query === '' ? '' : '?'.$query;
    }

    /**
     * @param  list<string>  $names  names the listing handles itself
     */
    public function queryWithout(array $names): string
    {
        return implode('&', $this->pairsWithout($names));
    }

    /**
     * Read from the raw query string: Laravel's middlewares trim values and turn empty ones into null, and `?s=` is still a search to WordPress.
     *
     * @param  list<string>  $names  names the listing handles itself
     * @return list<string> pairs as the URL encoded them
     */
    public function pairsWithout(array $names): array
    {
        $read = array_diff($this->reserved->wordPress(), [...$names, self::PAGED_QUERY_VAR]);

        return $this->pairsWhere(fn (int|string|null $name): bool => in_array($name, $read, true));
    }

    private function travels(int|string|null $name): bool
    {
        if ($name === null || $this->isAPageNumber($name)) {
            return false;
        }

        return $this->parameters->owns((string) $name) || in_array($name, $this->reserved->wordPress(), true);
    }

    private function isAPageNumber(int|string $name): bool
    {
        return $name === self::PAGED_QUERY_VAR || $name === $this->parameters->reserved(QueryParameter::Page);
    }

    /**
     * @param  callable(int|string|null): bool  $kept
     * @return list<string> pairs as the URL encoded them
     */
    private function pairsWhere(callable $kept): array
    {
        return array_values(array_filter($this->pairs(), fn (string $pair): bool => $kept($this->nameOf($pair))));
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
