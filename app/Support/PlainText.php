<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final readonly class PlainText
{
    private const string COMMENTS = '/<!--.*?-->/s';

    private const string EMBEDDED_CODE = '#<(script|style)\b[^>]*>.*?</\1>#si';

    private const string SPACES = '/[\s\p{Z}]+/u';

    private const string TAG_OPENING = '<';

    private const string SPACE = ' ';

    /**
     * WordPress stores post titles and term names HTML-encoded, and
     * `wptexturize()` adds more entities on the way out. Left as is, Blade
     * encodes the ampersand a second time and `&` reaches the page as `&amp;`.
     */
    public static function from(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Block delimiters are HTML comments: they go with the tags, and so do the attributes they carry. */
    public static function fromMarkup(string $markup): string
    {
        return self::collapseSpaces(self::from(self::withoutTags($markup)));
    }

    /**
     * Entities are left encoded, so a `&lt;` in the text never reads as a tag to whatever strips next.
     */
    public static function withoutTags(string $markup): string
    {
        $withoutComments = self::replace(self::COMMENTS, $markup);
        $withoutCode = self::replace(self::EMBEDDED_CODE, $withoutComments);

        // `<li>a</li><li>b</li>` would otherwise read as the single word `ab`.
        $separated = str_replace(self::TAG_OPENING, self::SPACE.self::TAG_OPENING, $withoutCode);

        return strip_tags($separated);
    }

    private static function collapseSpaces(string $text): string
    {
        return trim(self::replace(self::SPACES, $text));
    }

    private static function replace(string $pattern, string $subject): string
    {
        $replaced = preg_replace($pattern, self::SPACE, $subject);

        return $replaced ?? $subject;
    }
}
