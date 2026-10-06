<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Automattic\WooCommerce\Internal\ProductAttributesLookup\Filterer;
use Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore;
use Throwable;

final class VariationTaxonomies
{
    /** @var list<string>|null */
    private ?array $all = null;

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->all ??= $this->read();
    }

    /**
     * @return list<string>
     */
    private function read(): array
    {
        $attributes = array_values(wc_get_attribute_taxonomy_names());

        try {
            return $this->isLookupTableInUse()
                ? array_values(array_intersect($attributes, $this->markedInLookupTable()))
                : $attributes;
        } catch (Throwable) {
            // The lookup table is reached through WooCommerce's internal classes, which carry no compatibility promise.
            return $attributes;
        }
    }

    private function isLookupTableInUse(): bool
    {
        $isFiltering = wc_get_container()->get(Filterer::class)->filtering_via_lookup_table_is_active();

        return $isFiltering && ! $this->lookupTable()->regeneration_is_in_progress();
    }

    /**
     * @return list<string>
     */
    private function markedInLookupTable(): array
    {
        global $wpdb;

        $table = $this->lookupTable()->get_lookup_table_name();

        return $wpdb->get_col("SELECT DISTINCT taxonomy FROM {$table} WHERE is_variation_attribute = 1");
    }

    private function lookupTable(): LookupDataStore
    {
        return wc_get_container()->get(LookupDataStore::class);
    }
}
