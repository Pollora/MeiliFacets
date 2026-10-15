<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Pollora\Attributes\Filter;

/** The Customizer's « Default product sorting » offers only what the product listing can follow. */
final readonly class DefaultSortChoices
{
    /**
     * @param  array<string, string>  $choices
     * @return array<string, string>
     */
    #[Filter('woocommerce_default_catalog_orderby_options')]
    public function reproducible(array $choices): array
    {
        return array_filter(
            $choices,
            static fn (int|string $setting): bool => CatalogOrderSetting::of($setting)->isReproducible(),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
