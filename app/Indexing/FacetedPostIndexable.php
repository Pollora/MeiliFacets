<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\EngineFacetSort;
use Modules\MeiliFacets\Enums\FacetingSetting;
use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Enums\PaginationSetting;
use Modules\MeiliFacets\Enums\TypoToleranceSetting;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Support\UniqueList;
use Pollora\MeiliScout\Contracts\HasDependentDocuments;
use Pollora\MeiliScout\Indexables\PostIndexable;

final class FacetedPostIndexable extends PostIndexable implements HasDependentDocuments
{
    private const string ALL_FACETS = '*';

    private const string EVERY_FIELD = '*';

    /**
     * The only fields the module reads back from a hit. Anything else a project
     * needs is declared, not inherited.
     */
    private const array READ_BY_THE_MODULE = [
        DocumentField::Id->value,
        DocumentField::Card->value,
        DocumentField::ParentId->value,
    ];

    public function __construct(
        private readonly IndexAttributes $attributes,
        private readonly SearchableAttributes $searchable,
        private readonly IndexedTaxonomies $taxonomies,
        private readonly EngineLimits $limits,
        private readonly VariantDocuments $variants,
    ) {}

    public function dependentDocuments(array $document, mixed $item): array
    {
        return $this->variants->of($document);
    }

    public function dependentsFilter(array $itemIds): string
    {
        return $this->variants->filterOf($itemIds);
    }

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
            [DocumentField::ParentId->value],
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

        $settings[IndexSetting::Pagination->value] = [
            ...$settings[IndexSetting::Pagination->value] ?? [],
            PaginationSetting::MaxTotalHits->value => $this->limits->reachableHits,
        ];

        return $this->withSearchSettings($settings);
    }

    /**
     * A field left out cannot be searched at all, even by a query written with the public key.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withSearchSettings(array $settings): array
    {
        $settings[IndexSetting::SearchableAttributes->value] = $this->searchable->all();

        $settings[IndexSetting::TypoTolerance->value] = [
            ...$settings[IndexSetting::TypoTolerance->value] ?? [],
            TypoToleranceSetting::DisableOnAttributes->value => $this->attributes->exactlyMatched(),
        ];

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
            : UniqueList::merge(self::READ_BY_THE_MODULE, $declared);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  ...$lists
     * @return array<string, mixed>
     */
    private function mergeInto(array $settings, IndexSetting $setting, array ...$lists): array
    {
        $settings[$setting->value] = UniqueList::merge($settings[$setting->value] ?? [], ...$lists);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $faceting
     * @return array<string, mixed>
     */
    private function facetingSettings(array $faceting): array
    {
        // Meilisearch orders facet values alphabetically by default, and caps them at 100.
        return [
            ...$faceting,
            FacetingSetting::SortValuesBy->value => [
                self::ALL_FACETS => EngineFacetSort::ByCount->value,
            ],
            FacetingSetting::MaxValuesPerFacet->value => $this->limits->maxFacetValues,
        ];
    }

    /**
     * @return list<string>
     */
    private function facetAttributes(): array
    {
        return array_map(
            DocumentField::Facets->path(...),
            $this->taxonomies->all()
        );
    }
}
