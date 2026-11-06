<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Contracts\PageMetadata;

final readonly class WordPressPageMetadata implements PageMetadata
{
    private const string TITLE_SHORT_CIRCUIT = 'pre_get_document_title';

    private const string TITLE_PART = 'title';

    private const string PAGE_PART = 'page';

    public function __construct(private DocumentTitle $title) {}

    public function titles(): ?array
    {
        if ($this->aPluginWritesTheTitle()) {
            return null;
        }

        return [
            'first' => $this->titleWithPagePart(''),
            'paged' => $this->titleWithPagePart(sprintf(__('Page %s', 'default'), self::PAGE_PLACEHOLDER)),
        ];
    }

    /** `rel_canonical()` prints one on a singular page only, the same on every page of it. */
    public function canonicals(): ?array
    {
        $canonical = $this->printsItsCanonical() ? wp_get_canonical_url() : false;

        return is_string($canonical) ? ['first' => $canonical, 'paged' => $canonical] : null;
    }

    /** A title returned on `pre_get_document_title` never reaches the parts. */
    private function aPluginWritesTheTitle(): bool
    {
        return has_filter(self::TITLE_SHORT_CIRCUIT) !== false;
    }

    private function printsItsCanonical(): bool
    {
        return is_singular() && has_action('wp_head', 'rel_canonical') !== false;
    }

    /** WordPress puts the page right after the title, and drops an empty part. */
    private function titleWithPagePart(string $page): string
    {
        return $this->title->renderedWith('document_title_parts', static fn (array $parts): array => [
            ...array_intersect_key($parts, [self::TITLE_PART => true]),
            self::PAGE_PART => $page,
            ...array_diff_key($parts, [self::TITLE_PART => true, self::PAGE_PART => true]),
        ]);
    }
}
