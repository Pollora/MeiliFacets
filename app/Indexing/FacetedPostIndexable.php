<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Enums\ProductMeta;
use Modules\MeiliFacets\Support\WooCommerce;
use Pollora\MeiliScout\Config\Settings;
use Pollora\MeiliScout\Indexables\PostIndexable;

// `Indexer` delegates formatting on `instanceof PostIndexable`: an override of
// `formatForIndexing()` here would never run.
final class FacetedPostIndexable extends PostIndexable
{
    /** @var list<string>|null */
    private ?array $taxonomies = null;

    /**
     * @return array<string, mixed>
     */
    public function getIndexSettings(): array
    {
        $settings = parent::getIndexSettings();

        $settings[IndexSetting::FilterableAttributes->value] = $this->mergeUnique(
            $settings[IndexSetting::FilterableAttributes->value] ?? [],
            $this->facetAttributes(),
            $this->filterableProductAttributes()
        );

        $settings[IndexSetting::SortableAttributes->value] = $this->mergeUnique(
            $settings[IndexSetting::SortableAttributes->value] ?? [],
            $this->sortableProductAttributes()
        );

        return $settings;
    }

    /**
     * @return list<string>
     */
    private function facetAttributes(): array
    {
        return array_map(
            static fn (string $taxonomy): string => DocumentField::Facets->path($taxonomy),
            $this->indexedTaxonomies()
        );
    }

    /**
     * @return list<string>
     */
    private function filterableProductAttributes(): array
    {
        return WooCommerce::isActive() ? ProductMeta::paths() : [];
    }

    /**
     * @return list<string>
     */
    private function sortableProductAttributes(): array
    {
        return WooCommerce::isActive() ? [ProductMeta::Price->path()] : [];
    }

    /**
     * @return list<string>
     */
    private function indexedTaxonomies(): array
    {
        // Reached on every save through ensureIndexExists(), so resolved once.
        return $this->taxonomies ??= $this->resolveIndexedTaxonomies();
    }

    /**
     * @return list<string>
     */
    private function resolveIndexedTaxonomies(): array
    {
        $postTypes = array_values(Settings::get('indexed_post_types', []));
        $taxonomiesPerPostType = array_map(get_object_taxonomies(...), $postTypes);

        return $this->mergeUnique(...$taxonomiesPerPostType);
    }

    /**
     * @param  list<string>  ...$lists
     * @return list<string>
     */
    private function mergeUnique(array ...$lists): array
    {
        return array_values(array_unique(array_merge(...$lists)));
    }
}
