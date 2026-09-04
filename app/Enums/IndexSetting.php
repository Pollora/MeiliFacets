<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum IndexSetting: string
{
    case FilterableAttributes = 'filterableAttributes';
    case SortableAttributes = 'sortableAttributes';
    case DisplayedAttributes = 'displayedAttributes';
    case Faceting = 'faceting';
}
