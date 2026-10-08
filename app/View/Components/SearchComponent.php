<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\View\ElementId;

/** A brick of a search root: it names its root when the page holds more than one. */
abstract class SearchComponent extends ContractComponent
{
    public SearchRoot $root;

    public ElementId $ids;

    public function __construct(SearchRegistry $roots, string $name = '')
    {
        $this->root = $name === '' ? $roots->onlyOne() : $roots->named($name);
        $this->ids = new ElementId($this->root->name);
    }
}
