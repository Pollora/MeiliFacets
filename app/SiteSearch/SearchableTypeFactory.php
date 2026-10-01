<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Search\PublishedPosts;
use WP_Post_Type;

final readonly class SearchableTypeFactory
{
    public const string CARD = 'meilifacets::search.card';

    public function __construct(private AttributesToSearchOn $searchOn) {}

    /**
     * @throws NoFieldToSearch
     * @throws SearchTypeRefused
     */
    public function forPostType(string $postType): SearchableType
    {
        return $this->make($postType, PublishedPosts::of($postType), $this->ownFields($postType));
    }

    /**
     * @param  list<string>  $baseFilter
     * @param  list<string>  $wantedFields  narrowed to the search order, in its ranking
     *
     * @throws NoFieldToSearch
     * @throws SearchTypeRefused
     */
    public function make(string $postType, array $baseFilter, array $wantedFields): SearchableType
    {
        $labels = $this->registered($postType)->labels;

        return new SearchableType(
            postType: $postType,
            heading: $labels->name,
            seeAllLabel: $labels->all_items,
            baseFilter: $baseFilter,
            searchOn: $this->searchOn->among($wantedFields),
            card: self::CARD,
            archive: $this->archiveOf($postType),
        );
    }

    /**
     * @return list<string>
     */
    private function ownFields(string $postType): array
    {
        return [
            DocumentField::Title->value,
            ...DocumentField::Labels->paths(get_object_taxonomies($postType)),
            DocumentField::Excerpt->value,
        ];
    }

    private function registered(string $postType): WP_Post_Type
    {
        $object = get_post_type_object($postType);

        if (! $object instanceof WP_Post_Type) {
            throw SearchTypeRefused::unregistered($postType);
        }

        return $object;
    }

    private function archiveOf(string $postType): ?string
    {
        $address = get_post_type_archive_link($postType);

        return is_string($address) ? $address : null;
    }
}
