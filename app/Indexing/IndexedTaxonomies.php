<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Support\UniqueList;
use Pollora\MeiliScout\Config\Settings;

/** The taxonomies of every post type MeiliScout indexes, in the order WordPress registered them. */
final class IndexedTaxonomies
{
    /** @var list<string>|null */
    private ?array $taxonomies = null;

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
        $postTypes = array_values(Settings::get('indexed_post_types', []));
        $taxonomiesPerPostType = array_map(get_object_taxonomies(...), $postTypes);

        return UniqueList::merge(...$taxonomiesPerPostType);
    }
}
