<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Closure;

final readonly class DocumentTitle
{
    /** After the module's own `YoastPageCount`, whose real page numbers the placeholders replace. */
    private const int AFTER_EVERY_FILTER = PHP_INT_MAX;

    /**
     * @param  Closure(mixed): mixed  $filter
     */
    public function renderedWith(string $hook, Closure $filter): string
    {
        add_filter($hook, $filter, self::AFTER_EVERY_FILTER);

        try {
            $title = wp_get_document_title();
        } finally {
            remove_filter($hook, $filter, self::AFTER_EVERY_FILTER);
        }

        // WordPress escapes the title for the head, and Yoast SEO escapes its own.
        return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
