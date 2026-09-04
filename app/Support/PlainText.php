<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class PlainText
{
    /**
     * WordPress stores post titles and term names HTML-encoded, and
     * `wptexturize()` adds more entities on the way out. Left as is, Blade
     * encodes the ampersand a second time and `&` reaches the page as `&amp;`.
     */
    public static function from(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
