<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use LogicException;
use Modules\MeiliFacets\Support\NamedRegistry;

/**
 * The search roots rendered on the page, by name.
 *
 * @extends NamedRegistry<SearchRoot>
 */
final class SearchRegistry extends NamedRegistry
{
    public function open(SearchRoot $root): void
    {
        if (array_key_exists($root->name, $this->entries)) {
            throw new LogicException(
                "A search named \"{$root->name}\" is already rendered on this page: give each root its own name."
            );
        }

        $this->entries[$root->name] = $root;
    }

    protected function kind(): string
    {
        return 'search';
    }
}
