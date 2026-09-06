<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\FacetingSetting;
use Modules\MeiliFacets\Enums\FacetValueOrder;
use Modules\MeiliFacets\Enums\IndexSetting;
use Pollora\MeiliScout\Config\Settings;
use Pollora\MeiliScout\Indexables\PostIndexable;

// `Indexer` delegates formatting on `instanceof PostIndexable`: an override of
// `formatForIndexing()` here would never run.
final class FacetedPostIndexable extends PostIndexable
{
    private const string ALL_FACETS = '*';

    private const string EVERY_FIELD = '*';

    /**
     * The only fields the module reads back from a hit. Anything else a project
     * needs is declared, not inherited.
     */
    private const array READ_BY_THE_MODULE = ['ID', 'card'];

    /** @var list<string>|null */
    private ?array $taxonomies = null;

    public function __construct(private readonly IndexAttributes $attributes) {}

    /**
     * @return array<string, mixed>
     */
    public function getIndexSettings(): array
    {
        $settings = parent::getIndexSettings();

        $settings = $this->mergeInto(
            $settings,
            IndexSetting::FilterableAttributes,
            $this->facetAttributes(),
            $this->attributes->filterable()
        );

        $settings = $this->mergeInto(
            $settings,
            IndexSetting::SortableAttributes,
            $this->attributes->sortable()
        );

        $settings[IndexSetting::DisplayedAttributes->value] = $this->displayedAttributes();

        $settings[IndexSetting::Faceting->value] = $this->facetingSettings(
            $settings[IndexSetting::Faceting->value] ?? []
        );

        return $settings;
    }

    /**
     * @return list<string>
     */
    private function displayedAttributes(): array
    {
        $declared = $this->attributes->displayed();

        return in_array(self::EVERY_FIELD, $declared, true)
            ? [self::EVERY_FIELD]
            : $this->mergeUnique(self::READ_BY_THE_MODULE, $declared);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  ...$lists
     * @return array<string, mixed>
     */
    private function mergeInto(array $settings, IndexSetting $setting, array ...$lists): array
    {
        $settings[$setting->value] = $this->mergeUnique($settings[$setting->value] ?? [], ...$lists);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $faceting
     * @return array<string, mixed>
     */
    private function facetingSettings(array $faceting): array
    {
        // Meilisearch orders facet values alphabetically by default.
        return [
            ...$faceting,
            FacetingSetting::SortValuesBy->value => [
                self::ALL_FACETS => FacetValueOrder::ByCount->value,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function facetAttributes(): array
    {
        return array_map(
            DocumentField::Facets->path(...),
            $this->indexedTaxonomies()
        );
    }

    /**
     * @return list<string>
     */
    private function indexedTaxonomies(): array
    {
        // Reached on every save through ensureIndexExists().
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
