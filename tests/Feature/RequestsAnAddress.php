<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use PHPUnit\Framework\Attributes\After;

/** `get_pagenum_link()` reads the request and the rewrite settings from globals, which the whole suite shares. */
trait RequestsAnAddress
{
    /** @var array{string, string, bool, string}|null */
    private ?array $kept = null;

    /** Set in memory: `set_permalink_structure()` saves the option, and the host project's site with it. */
    protected function requesting(string $requestUri, string $paginationBase = 'page'): void
    {
        global $wp_rewrite;

        $this->kept ??= [$_SERVER['REQUEST_URI'] ?? '', $wp_rewrite->permalink_structure, $wp_rewrite->use_trailing_slashes, $wp_rewrite->pagination_base];

        $_SERVER['REQUEST_URI'] = $requestUri;
        $wp_rewrite->permalink_structure = '/%postname%';
        $wp_rewrite->use_trailing_slashes = false;
        $wp_rewrite->pagination_base = $paginationBase;
    }

    #[After(1)]
    protected function restoreTheAddress(): void
    {
        global $wp_rewrite;

        if ($this->kept === null) {
            return;
        }

        [$_SERVER['REQUEST_URI'], $wp_rewrite->permalink_structure, $wp_rewrite->use_trailing_slashes, $wp_rewrite->pagination_base] = $this->kept;
        $this->kept = null;
    }
}
