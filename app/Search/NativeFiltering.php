<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Pollora\Attributes\Filter;

/**
 * WooCommerce filters the main query by price and by attribute in SQL, reading
 * `min_price`, `max_price` and `filter_*` straight from `$_GET`
 * (`class-wc-query.php:588` and `:604-607`). The engine answers the same
 * question here, so that work is thrown away — and it leaves `found_posts`
 * narrowed by a filter the page never rendered.
 *
 * The hook only exists while a product query is running, so this is inert on
 * every other listing and on a site without WooCommerce.
 */
final readonly class NativeFiltering
{
    #[Filter('woocommerce_enable_post_clause_filtering')]
    public function leaveTheMainQueryAlone(): bool
    {
        return false;
    }
}
