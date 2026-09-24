<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Pollora\Attributes\Filter;

/** Without it, WooCommerce also filters a product archive's main query from `$_GET`, narrowing `found_posts`. */
final readonly class NativeFiltering
{
    #[Filter('woocommerce_enable_post_clause_filtering')]
    public function leaveTheMainQueryAlone(): bool
    {
        return false;
    }
}
