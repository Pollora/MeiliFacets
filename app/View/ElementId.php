<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/**
 * Identifiers a template hands to `aria-*` attributes. They carry the listing
 * name because a page may hold two listings, and nothing else may collide.
 */
final readonly class ElementId
{
    private const string PREFIX = 'meilifacets';

    public function __construct(private string $listing) {}

    public function sort(): string
    {
        return $this->of('sort');
    }

    public function facetCount(string $taxonomy, string $value): string
    {
        return $this->of($taxonomy, $value);
    }

    private function of(string ...$parts): string
    {
        return implode('-', [self::PREFIX, $this->listing, ...$parts]);
    }
}
