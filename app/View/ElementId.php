<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

/**
 * Identifiers a template hands to `aria-*` attributes. They carry the name of
 * their listing or search root because a page may hold two, and nothing else may collide.
 */
final readonly class ElementId
{
    private const string PREFIX = 'meilifacets';

    public function __construct(private string $root) {}

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

    public function sortPanel(): string
    {
        return $this->of('sort', 'panel');
    }

    public function sortChoiceName(): string
    {
        return $this->of('sort', 'choice');
    }

    public function drawer(): string
    {
        return $this->of('drawer');
    }

    public function drawerTitle(): string
    {
        return $this->of('drawer', 'title');
    }

    public function drawerCount(): string
    {
        return $this->of('drawer', 'count');
    }

    public function applyCount(): string
    {
        return $this->of('apply', 'count');
    }

    public function searchPanel(): string
    {
        return $this->of('search', 'panel');
    }

    public function searchInput(): string
    {
        return $this->of('search', 'input');
    }

    public function searchHeading(string $postType): string
    {
        return $this->of('search', $postType, 'heading');
    }

    public function searchListbox(string $postType): string
    {
        return $this->of('search', $postType, 'results');
    }

    public function facetPanel(string $facet): string
    {
        return $this->facetScope($facet).'panel';
    }

    public function facetSelectedCount(string $facet): string
    {
        return $this->facetScope($facet).'selected';
    }

    public function facetCount(string $facet, string $value): string
    {
        return $this->facetScope($facet).'count-'.$value;
    }

    public function facetValueLabel(string $facet, string $value): string
    {
        return $this->facetScope($facet).'label-'.$value;
    }

    /** `sanitize_title()` collapses hyphen runs, so no term slug ever holds `--`. */
    private function facetScope(string $facet): string
    {
        return $this->of('facet', $facet).'--';
    }

    private function of(string ...$parts): string
    {
        return implode('-', [self::PREFIX, $this->root, ...$parts]);
    }
}
