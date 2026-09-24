<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;

/** `wc_products_array_filter_visible_grouped()` counts a child the signed-in user may edit, not only a published one. */
final readonly class AnonymousVisitor
{
    private const int NOBODY = 0;

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public function during(Closure $read): mixed
    {
        if (! function_exists('wp_set_current_user') || ! function_exists('get_current_user_id')) {
            return $read();
        }

        $signedIn = get_current_user_id();
        wp_set_current_user(self::NOBODY);

        try {
            return $read();
        } finally {
            wp_set_current_user($signedIn);
        }
    }
}
