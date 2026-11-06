<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Listing\Pagination;
use Pollora\Attributes\Filter;

/** Yoast SEO counts `$wp_query->max_num_pages`, which ignores the listing's filters. */
final readonly class YoastPageCount
{
    private const array COUNTED = [YoastPageWords::VARIABLE => true, YoastPageWords::TOTAL_VARIABLE => true];

    public function __construct(private ListingPage $page, private YoastPageWords $words) {}

    /**
     * @param  array<string, mixed>  $replacements
     * @return array<string, mixed>
     */
    #[Filter('wpseo_replacements')]
    public function countTheEnginePages(array $replacements): array
    {
        $pagination = $this->page->pagination();
        $asksForPages = array_intersect_key(self::COUNTED, $replacements) !== [];

        if (! $pagination instanceof Pagination || ! $asksForPages) {
            return $replacements;
        }

        return [...$replacements, ...array_intersect_key($this->variables($pagination), $replacements)];
    }

    /**
     * @return array<string, string>
     */
    private function variables(Pagination $pagination): array
    {
        $page = $pagination->asked;
        $pages = $pagination->pages();
        $named = $this->words->names($page, $pages);

        return [
            YoastPageWords::VARIABLE => $named ? $this->words->of($page, $pages) : '',
            YoastPageWords::TOTAL_VARIABLE => $pages > 0 ? (string) $pages : '',
        ];
    }
}
