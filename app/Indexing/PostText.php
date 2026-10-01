<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Support\PlainText;
use WP_Post;

/** What a post says, without its markup: block delimiters, tags, shortcodes and entities out. */
final readonly class PostText
{
    private const string ELLIPSIS = '…';

    public function content(WP_Post $post): string
    {
        if ($this->isProtected($post)) {
            return '';
        }

        return $this->plain($post->post_content);
    }

    public function excerpt(WP_Post $post): string
    {
        if ($this->isProtected($post)) {
            return '';
        }

        return $this->plain($post->post_excerpt);
    }

    /** The excerpt the author wrote, whole; else the opening of the content — as `get_the_excerpt()` does. */
    public function summary(WP_Post $post, int $openingWords): string
    {
        if ($this->isProtected($post)) {
            return '';
        }

        $ownExcerpt = $this->excerpt($post);

        if ($ownExcerpt !== '') {
            return $ownExcerpt;
        }

        return $this->opening($post->post_content, $openingWords);
    }

    private function opening(string $markup, int $words): string
    {
        $text = PlainText::withoutTags(strip_shortcodes($markup));

        // Counted before decoding: `wp_trim_words()` strips tags, and a decoded `&lt;` would read as one.
        return PlainText::fromMarkup(wp_trim_words($text, $words, self::ELLIPSIS));
    }

    private function isProtected(WP_Post $post): bool
    {
        return $post->post_password !== '';
    }

    private function plain(string $markup): string
    {
        return PlainText::fromMarkup(strip_shortcodes($markup));
    }
}
