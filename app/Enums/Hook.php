<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

use Illuminate\Support\HtmlString;

/** The anchors the browser client addresses: classes belong to the theme and change with it. */
enum Hook: string
{
    case Results = 'results';
    case CardTemplate = 'card-template';
    case Empty = 'empty';

    case Card = 'card';
    case Url = 'url';
    case Image = 'image';
    case Title = 'title';
    case Price = 'price';

    case Facets = 'facets';
    case FacetValue = 'facet-value';
    case Input = 'input';
    case Count = 'count';
    case Apply = 'apply';

    case Pagination = 'pagination';
    case PageTemplate = 'page-template';
    case Page = 'page';
    case Previous = 'previous';
    case Next = 'next';

    case Sort = 'sort';
    case SortTrigger = 'sort-trigger';
    case SortList = 'sort-list';
    case SortOption = 'sort-option';
    case Reset = 'reset';
    case ActiveFilters = 'active-filters';

    public function attribute(): HtmlString
    {
        return new HtmlString(Contract::ATTRIBUTE.'="'.$this->value.'"');
    }
}
