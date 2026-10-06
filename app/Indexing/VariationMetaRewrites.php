<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Search\VisibleProducts;
use Modules\MeiliFacets\Support\WooCommerce;
use Pollora\Attributes\Action;
use WP_Term;

/** WooCommerce rewrites the attribute metas of products and variations in SQL, and leaves their cache stale. */
final class VariationMetaRewrites
{
    private const string POST_META_CACHE = 'post_meta';

    private const string SCHEDULE_INDEXATION = 'meiliscout/schedule_indexation';

    /** WooCommerce rewrites the metas at priority 10, MeiliScout reads them at `EDITED_TERM_PRIORITY`. */
    private const int AFTER_TERM_REWRITE = 20;

    /** @var array<string, true> */
    private array $renamedMetaKeys = [];

    #[Action('edited_term', priority: self::AFTER_TERM_REWRITE)]
    public function clearMetaCacheOfTermProducts(int $termId, int $termTaxonomyId, string $taxonomy): void
    {
        if (! $this->isProductAttribute($taxonomy)) {
            return;
        }

        $term = get_term($termId, $taxonomy);

        if (! $term instanceof WP_Term) {
            return;
        }

        $metaKey = wc_variation_attribute_name($taxonomy);

        $this->clearMetaCache($this->productsOf($term));
        $this->clearMetaCache($this->variationsWithValue($metaKey, $term->slug));
    }

    /**
     * WooCommerce fires this before it moves the variation metas to the new attribute key.
     *
     * @param  array{attribute_name: string}  $attribute
     */
    #[Action('woocommerce_attribute_updated')]
    public function rememberRenamedAttribute(int $attributeId, array $attribute, string $oldSlug): void
    {
        if ($attribute['attribute_name'] === $oldSlug) {
            return;
        }

        $taxonomy = wc_attribute_taxonomy_name($attribute['attribute_name']);
        $this->renamedMetaKeys[wc_variation_attribute_name($taxonomy)] = true;
    }

    #[Action('shutdown')]
    public function reindexRenamedAttributes(): void
    {
        if ($this->renamedMetaKeys === []) {
            return;
        }

        $this->clearMetaCacheOfRenamedAttributes();
        $this->renamedMetaKeys = [];

        do_action(self::SCHEDULE_INDEXATION);
    }

    private function clearMetaCacheOfRenamedAttributes(): void
    {
        foreach (array_keys($this->renamedMetaKeys) as $metaKey) {
            $this->clearMetaCache($this->variationsWithKey($metaKey));
        }
    }

    private function isProductAttribute(string $taxonomy): bool
    {
        return WooCommerce::isActive() && taxonomy_is_product_attribute($taxonomy);
    }

    private function productsOf(WP_Term $term): array
    {
        $productIds = get_objects_in_term($term->term_id, $term->taxonomy);

        if (is_wp_error($productIds)) {
            return [];
        }

        return array_map(intval(...), $productIds);
    }

    private function variationsWithKey(string $metaKey): array
    {
        return $this->variationIds(['meta_key' => $metaKey]);
    }

    private function variationsWithValue(string $metaKey, string $value): array
    {
        return $this->variationIds(['meta_key' => $metaKey, 'meta_value' => $value]);
    }

    private function variationIds(array $criteria): array
    {
        return get_posts([
            ...$criteria,
            'post_type' => VisibleProducts::VARIATION_POST_TYPE,
            'post_status' => 'any',
            'nopaging' => true,
            'fields' => 'ids',
        ]);
    }

    private function clearMetaCache(array $postIds): void
    {
        wp_cache_delete_multiple($postIds, self::POST_META_CACHE);
    }
}
