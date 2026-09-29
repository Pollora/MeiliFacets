<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use LogicException;
use Modules\MeiliFacets\Support\NamedRegistry;

/**
 * The search roots rendered on the page, by name, and the sections placed in each.
 *
 * @extends NamedRegistry<SearchRoot>
 */
final class SearchRegistry extends NamedRegistry
{
    /** @var array<string, array<string, true>> post types by root name */
    private array $sections = [];

    public function open(SearchRoot $root): void
    {
        if (array_key_exists($root->name, $this->entries)) {
            throw new LogicException(
                "A search named \"{$root->name}\" is already rendered on this page: give each root its own name."
            );
        }

        $this->entries[$root->name] = $root;
    }

    public function placeSection(SearchRoot $root, SearchableType $type): void
    {
        if (isset($this->sections[$root->name][$type->postType])) {
            throw new LogicException(
                "The search \"{$root->name}\" already holds a section for \"{$type->postType}\": place one section per type."
            );
        }

        $this->sections[$root->name][$type->postType] = true;
    }

    protected function kind(): string
    {
        return 'search';
    }
}
