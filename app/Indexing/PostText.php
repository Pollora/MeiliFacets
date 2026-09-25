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

    /** The excerpt the author wrote, else the opening of the content — as `wp_trim_excerpt()` does. */
    public function summary(WP_Post $post, int $words): string
    {
        if ($this->isProtected($post)) {
            return '';
        }

        $source = $this->hasOwnExcerpt($post) ? $post->post_excerpt : $post->post_content;

        // Counted before decoding: `wp_trim_words()` strips tags, and a decoded `&lt;` would read as one.
        $bounded = wp_trim_words(PlainText::withoutTags(strip_shortcodes($source)), $words, self::ELLIPSIS);

        return PlainText::fromMarkup($bounded);
    }

    /** The card is public and the excerpt and content searchable with the public key: neither may reveal what a password guards. */
    private function isProtected(WP_Post $post): bool
    {
        return $post->post_password !== '';
    }

    private function hasOwnExcerpt(WP_Post $post): bool
    {
        return $this->excerpt($post) !== '';
    }

    private function plain(string $markup): string
    {
        return PlainText::fromMarkup(strip_shortcodes($markup));
    }
}
