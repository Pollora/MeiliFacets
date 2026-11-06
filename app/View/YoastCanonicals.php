<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Contracts\PageMetadata;
use Yoast\WP\SEO\Models\Indexable;

/**
 * Yoast SEO pages an archive's canonical with `get_paginated_url()`, and keeps a custom one, or a singular page's,
 * on every page (`Indexable_*_Presentation::generate_canonical()`). It reads the page number from the main query
 * only: the model is checked against the canonical it printed instead.
 *
 * @phpstan-import-type ByPage from PageMetadata
 */
final readonly class YoastCanonicals
{
    /** The number WooCommerce templates a page link with (`templates/loop/pagination.php`). */
    private const int PAGE_SENTINEL = 999999999;

    /**
     * @return ByPage|null
     */
    public function byPage(): ?array
    {
        $meta = YoastSEO()->meta->for_current_page();
        $printed = (string) $meta->canonical;

        if ($printed === '') {
            return null;
        }

        if ($this->isTheSameOnEveryPage($meta->indexable)) {
            return ['first' => $printed, 'paged' => $printed];
        }

        $permalink = (string) $meta->permalink;

        // A `wpseo_canonical` filter may have printed something the model cannot tell.
        if ($this->canonicalOf($permalink, $this->servedPage()) !== $printed) {
            return null;
        }

        return ['first' => $permalink, 'paged' => $this->pagedPattern($permalink)];
    }

    private function isTheSameOnEveryPage(Indexable $indexable): bool
    {
        $isCustom = (string) $indexable->canonical !== '';

        return $isCustom || is_singular();
    }

    private function canonicalOf(string $permalink, int $page): string
    {
        if ($page <= 1) {
            return $permalink;
        }

        return urldecode(YoastSEO()->helpers->pagination->get_paginated_url($permalink, $page));
    }

    private function pagedPattern(string $permalink): string
    {
        $page = YoastSEO()->helpers->pagination->get_paginated_url($permalink, self::PAGE_SENTINEL);

        return str_replace((string) self::PAGE_SENTINEL, PageMetadata::PAGE_PLACEHOLDER, urldecode($page));
    }

    private function servedPage(): int
    {
        return YoastSEO()->helpers->pagination->get_current_archive_page_number();
    }
}
