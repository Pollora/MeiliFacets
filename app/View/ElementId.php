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

    public function sortLabel(): string
    {
        return $this->of('sort', 'label');
    }

    public function sortTrigger(): string
    {
        return $this->of('sort', 'trigger');
    }

    public function sortList(): string
    {
        return $this->of('sort', 'list');
    }

    public function sortOption(string $key): string
    {
        return $this->of('sort', 'option', $key);
    }

    public function facetPanel(string $facet): string
    {
        return $this->facetScope($facet).'panel';
    }

    public function facetCount(string $facet, string $value): string
    {
        return $this->facetScope($facet).'count-'.$value;
    }

    /** `sanitize_title()` collapses hyphen runs, so no term slug ever holds `--`. */
    private function facetScope(string $facet): string
    {
        return $this->of('facet', $facet).'--';
    }

    private function of(string ...$parts): string
    {
        return implode('-', [self::PREFIX, $this->listing, ...$parts]);
    }
}
