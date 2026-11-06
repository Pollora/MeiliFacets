<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

/**
 * What the site writes in the head of a listing's pages, for the client to write on the pages it turns to.
 * The module binds one for WordPress alone and one for Yoast SEO; a project on another SEO plugin binds its own.
 *
 * @phpstan-type ByPage array{first: string, paged: string}
 */
interface PageMetadata
{
    public const string PAGE_PLACEHOLDER = ':page';

    public const string PAGE_COUNT_PLACEHOLDER = ':pages';

    /**
     * Plain text: the first page's title, and a later page's with its number and the page count as placeholders.
     * `null` when the titles cannot be told.
     *
     * @return ByPage|null
     */
    public function titles(): ?array;

    /**
     * The first page's canonical URL, and a later page's with its number as a placeholder; the same URL twice when
     * every page shares one. `null` when the page prints none, or when it cannot be told.
     *
     * @return ByPage|null
     */
    public function canonicals(): ?array;
}
