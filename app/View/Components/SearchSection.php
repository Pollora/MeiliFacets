<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use LogicException;
use Modules\MeiliFacets\SiteSearch\SearchableType;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchTypeRefused;

final class SearchSection extends SearchComponent
{
    private const int FEWEST_RESULTS = 1;

    public SearchableType $type;

    public int $limit;

    /**
     * @throws SearchTypeRefused
     */
    public function __construct(SearchRegistry $roots, string $type, ?int $limit = null, string $name = '')
    {
        parent::__construct($roots, $name);

        $this->type = $this->root->type($type);
        $this->limit = $this->limitOf($limit ?? $this->root->settings->limit);
        $roots->placeSection($this->root, $this->type);
    }

    public function render(): View
    {
        return view('meilifacets::components.search-section', [
            'headingId' => $this->ids->searchHeading($this->type->postType),
            'listboxId' => $this->ids->searchListbox($this->type->postType),
        ]);
    }

    private function limitOf(int $limit): int
    {
        if ($limit < self::FEWEST_RESULTS) {
            throw new LogicException(
                "The search section for \"{$this->type->postType}\" asks for {$limit} results: it shows at least ".self::FEWEST_RESULTS.'.'
            );
        }

        return $limit;
    }
}
