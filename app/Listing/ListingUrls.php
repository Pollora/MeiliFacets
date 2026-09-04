<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Support\UrlParameters;

/**
 * Only the query string is rewritten: the path belongs to WordPress.
 */
final readonly class ListingUrls
{
    private const string VALUE_SEPARATOR = ',';

    public function __construct(
        private UrlParameters $parameters,
        private string $path,
    ) {}

    public function page(ListingState $state, int $page): string
    {
        return $this->build($state, [$this->parameters->reserved(QueryParameter::Page) => (string) $page]);
    }

    public function sort(ListingState $state, string $sort): string
    {
        return $this->build($state, [
            $this->parameters->reserved(QueryParameter::Sort) => $sort,
            $this->parameters->reserved(QueryParameter::Page) => (string) ListingState::FIRST_PAGE,
        ]);
    }

    public function reset(): string
    {
        return $this->path;
    }

    public function parameterFor(string $taxonomy): string
    {
        return $this->parameters->for($taxonomy);
    }

    public function pageParameter(): string
    {
        return $this->parameters->reserved(QueryParameter::Page);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function build(ListingState $state, array $overrides): string
    {
        $query = array_filter([...$this->fromState($state), ...$overrides], strlen(...));

        // The first page is the bare URL.
        if ((int) ($query[$this->pageParameter()] ?? ListingState::FIRST_PAGE) === ListingState::FIRST_PAGE) {
            unset($query[$this->pageParameter()]);
        }

        // Commas are legal in a query string: keeping them unescaped keeps the URL readable.
        $search = str_replace('%2C', self::VALUE_SEPARATOR, http_build_query($query));

        return $search === '' ? $this->path : $this->path.'?'.$search;
    }

    /**
     * @return array<string, string>
     */
    private function fromState(ListingState $state): array
    {
        $query = [];

        foreach ($state->facets as $taxonomy => $values) {
            sort($values);
            $query[$this->parameters->for($taxonomy)] = implode(self::VALUE_SEPARATOR, $values);
        }

        $query[$this->parameters->reserved(QueryParameter::Sort)] = $state->sort ?? '';
        $query[$this->parameters->reserved(QueryParameter::Query)] = $state->query;

        return $query;
    }
}
