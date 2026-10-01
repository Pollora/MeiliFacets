<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Support\UniqueList;

/** The taxonomies of every post type MeiliScout indexes, in the order WordPress registered them. */
final class IndexedTaxonomies
{
    /** @var list<string>|null */
    private ?array $resolvedFor = null;

    /** @var list<string> */
    private array $taxonomies = [];

    public function __construct(private readonly IndexedPostTypes $postTypes) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $postTypes = $this->postTypes->all();

        // Reached on every save through ensureIndexExists().
        if ($postTypes !== $this->resolvedFor) {
            $this->taxonomies = $this->resolve($postTypes);
            $this->resolvedFor = $postTypes;
        }

        return $this->taxonomies;
    }

    /**
     * Every indexed taxonomy but the technical ones: a `pa_*` attribute without archives included.
     *
     * @return list<string>
     */
    public function labelled(): array
    {
        return array_values(array_diff($this->all(), ProductTaxonomy::technical()));
    }

    /**
     * @param  list<string>  $postTypes
     * @return list<string>
     */
    private function resolve(array $postTypes): array
    {
        $taxonomiesPerPostType = array_map(get_object_taxonomies(...), $postTypes);

        return UniqueList::merge(...$taxonomiesPerPostType);
    }
}
