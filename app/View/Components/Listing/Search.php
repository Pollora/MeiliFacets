<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Http\PageAddress;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\View\Components\ListingComponent;
use Modules\MeiliFacets\View\StateFields;

final class Search extends ListingComponent
{
    public function __construct(
        CurrentListing $listings,
        private readonly PageAddress $page,
        private readonly UrlParameters $parameters,
        private readonly StateFields $stateFields,
        string $name = '',
        private readonly ?string $label = null,
    ) {
        parent::__construct($listings, $name);
    }

    public function shouldRender(): bool
    {
        return ! $this->listing->isRoutedSearch();
    }

    public function label(): string
    {
        return $this->label ?? __('Search this list');
    }

    public function render(): View
    {
        return view('meilifacets::components.listing.search', [
            'action' => $this->page->path(),
            'kept' => [
                ...$this->page->fieldsWithout($this->parameters->all()),
                ...$this->stateFields->of($this->listing),
            ],
            'parameter' => $this->listing->parameterForReserved(QueryParameter::Query),
            'term' => $this->listing->typedTerm(),
            'inputId' => $this->ids->listingSearchInput(),
            'maxLength' => StateReader::MAX_QUERY_LENGTH,
        ]);
    }
}
