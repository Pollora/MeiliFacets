<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

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
     * @return list<string>
     */
    private function resolve(): array
    {
        $postTypes = array_values(Settings::get('indexed_post_types', []));
        $taxonomiesPerPostType = array_map(get_object_taxonomies(...), $postTypes);

        return array_values(array_unique(array_merge(...$taxonomiesPerPostType)));
    }
}
