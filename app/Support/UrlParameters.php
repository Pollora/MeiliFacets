<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final class UrlParameters
{
    /**
     * @param  array<string, string>  $mapping
     */
    public function __construct(private readonly array $mapping) {}

    public static function fromConfig(): self
    {
        return new self(config('meilifacets.url_parameters', []));
    }

    public function for(string $taxonomy): string
    {
        return $this->mapping[$taxonomy] ?? $taxonomy;
    }

    /**
     * @param  list<string>  $taxonomies
     * @return array<string, string>
     */
    public function forMany(array $taxonomies): array
    {
        return array_combine($taxonomies, array_map($this->for(...), $taxonomies));
    }
}
