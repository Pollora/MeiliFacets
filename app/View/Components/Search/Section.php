<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Search;

use Illuminate\Contracts\View\View;
use LogicException;
use Modules\MeiliFacets\SiteSearch\SearchableType;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchTypeRefused;
use Modules\MeiliFacets\View\Components\SearchComponent;

final class Section extends SearchComponent
{
    private const int FEWEST_RESULTS = 1;

    public SearchableType $type;

    public int $limit;

    /**
     * @throws SearchTypeRefused
     */
    public function __construct(SearchRegistry $roots, string $type, int|float|string|bool|null $limit = null, string $name = '')
    {
        parent::__construct($roots, $name);

        $this->type = $this->root->type($type);
        $this->limit = $this->limitOf($limit ?? $this->root->settings->limit);
        $roots->placeSection($this->root, $this->type);
    }

    public function render(): View
    {
        return view('meilifacets::components.search.section', [
            'headingId' => $this->ids->searchHeading($this->type->postType),
            'listboxId' => $this->ids->searchListbox($this->type->postType),
        ]);
    }

    /** PHP truncates a float passed to an `int` with only a deprecation. */
    private function limitOf(int|float|string|bool $limit): int
    {
        if (is_bool($limit)) {
            throw $this->refusedLimit(var_export($limit, true));
        }

        $wholeLimit = filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => self::FEWEST_RESULTS]]);

        if ($wholeLimit === false) {
            throw $this->refusedLimit((string) $limit);
        }

        return $wholeLimit;
    }

    private function refusedLimit(string $limit): LogicException
    {
        return new LogicException(
            "The search section for \"{$this->type->postType}\" asks for {$limit} results: it shows a whole number of them, at least ".self::FEWEST_RESULTS.'.'
        );
    }
}
