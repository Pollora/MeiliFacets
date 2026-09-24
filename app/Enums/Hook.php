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
    case NoResults = 'no-results';
    case PastTheEnd = 'past-the-end';

    case Card = 'card';
    case Url = 'url';
    case Image = 'image';
    case Title = 'title';
    case Price = 'price';

    case Facets = 'facets';
    case Facet = 'facet';
    case FacetValue = 'facet-value';
    case Input = 'input';
    case Count = 'count';
    case More = 'more';
    case MoreLabel = 'more-label';
    case LessLabel = 'less-label';
    case Apply = 'apply';

    case Pagination = 'pagination';
    case Page = 'page';
    case Previous = 'previous';
    case Next = 'next';

    case Sort = 'sort';
    case SortTrigger = 'sort-trigger';
    case SortList = 'sort-list';
    case SortOption = 'sort-option';
    case PriceRange = 'price-range';
    case PriceTrack = 'price-track';
    case PriceHandle = 'price-handle';
    case PriceTip = 'price-tip';
    case PriceReadout = 'price-readout';
    case PriceBoundsMin = 'price-bounds-min';
    case PriceBoundsMax = 'price-bounds-max';
    case PriceMin = 'price-min';
    case PriceMax = 'price-max';
    case Reset = 'reset';
    case ActiveFilters = 'active-filters';
    case Total = 'total';

    public function attribute(): HtmlString
    {
        return new HtmlString(Contract::Attribute->value.'="'.$this->value.'"');
    }
}
