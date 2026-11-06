<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Contracts\PageMetadata;

/** `WPSEO_Replace_Vars::retrieve_page()`, which the module cannot call. */
final readonly class YoastPageWords
{
    public const string VARIABLE = '%%page%%';

    public const string NUMBER_VARIABLE = '%%pagenumber%%';

    public const string TOTAL_VARIABLE = '%%pagetotal%%';

    private const string FORMAT = 'Page %1$d of %2$d';

    private const string TEXT_DOMAIN = 'wordpress-seo';

    public function names(int $page, int $pages): bool
    {
        return $pages > 1 && $page > 1;
    }

    public function of(int $page, int $pages): string
    {
        return sprintf($this->format(), $page, $pages);
    }

    public function withPlaceholders(): string
    {
        // A translation numbers its arguments or not: each `%d` becomes a string slot.
        $slots = (string) preg_replace('/%(\d+\$)?d/', '%$1s', $this->format());

        return sprintf($slots, PageMetadata::PAGE_PLACEHOLDER, PageMetadata::PAGE_COUNT_PLACEHOLDER);
    }

    private function format(): string
    {
        return YoastSEO()->helpers->options->get_title_separator().' '.__(self::FORMAT, self::TEXT_DOMAIN);
    }
}
