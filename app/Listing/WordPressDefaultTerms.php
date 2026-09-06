<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\DefaultTerms;
use WP_Term;

final class WordPressDefaultTerms implements DefaultTerms
{
    /**
     * Two conventions name the same thing: `default_term_<taxonomy>` since the
     * `default_term` argument of `register_taxonomy`, and `default_<taxonomy>`
     * for the ones that predate it — `default_category`, `default_product_cat`.
     */
    private const array OPTIONS = ['default_term_%s', 'default_%s'];

    /** @var array<string, ?string> */
    private array $slugs = [];

    public function slugOf(string $taxonomy): ?string
    {
        return $this->slugs[$taxonomy] ??= $this->resolve($taxonomy);
    }

    private function resolve(string $taxonomy): ?string
    {
        foreach (self::OPTIONS as $option) {
            $term = get_term((int) get_option(sprintf($option, $taxonomy)), $taxonomy);

            if ($term instanceof WP_Term) {
                return $term->slug;
            }
        }

        return null;
    }
}
