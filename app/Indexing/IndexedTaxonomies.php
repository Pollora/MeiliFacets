<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Support\UniqueList;

/** The taxonomies of every post type MeiliScout indexes, in the order WordPress registered them. */
final class IndexedTaxonomies
{
    /** @var list<string>|null */
    private ?array $taxonomies = null;

    public function __construct(private readonly IndexedPostTypes $postTypes) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        // Reached on every save through ensureIndexExists().
        return $this->taxonomies ??= $this->resolve();
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
     * @return list<string>
     */
    private function resolve(): array
    {
        $taxonomiesPerPostType = array_map(get_object_taxonomies(...), $this->postTypes->all());

        return UniqueList::merge(...$taxonomiesPerPostType);
    }
}
