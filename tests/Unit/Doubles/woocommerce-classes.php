<?php

declare(strict_types=1);

// WooCommerce's internal lookup-table store, which the module reaches through the container: loaded by
// `WordPress::bootWooCommerce()`, from a test that runs in its own process only.

namespace Automattic\WooCommerce\Internal\ProductAttributesLookup {
    if (! class_exists(LookupDataStore::class)) {
        class LookupDataStore
        {
            public static bool $regenerating = false;

            public function regeneration_is_in_progress(): bool
            {
                return self::$regenerating;
            }

            public function get_lookup_table_name(): string
            {
                return 'wp_wc_product_attributes_lookup';
            }
        }

        class Filterer
        {
            public static bool $active = false;

            public function filtering_via_lookup_table_is_active(): bool
            {
                return self::$active;
            }
        }
    }
}
