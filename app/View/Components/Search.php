<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\ScriptModule;
use Modules\MeiliFacets\Enums\Stylesheet;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\View\ClientScript;
use Modules\MeiliFacets\View\ClientStylesheet;
use Modules\MeiliFacets\View\SiteSearchDescription;

final class Search extends ContractComponent
{
    public SearchRoot $root;

    public function __construct(
        SearchRegistry $roots,
        SearchSettings $defaults,
        AcceptedSearchTypes $types,
        ClientStylesheet $stylesheet,
        ClientScript $script,
        SiteSearchDescription $description,
        string $name = SearchRoot::DEFAULT_NAME,
        ?int $minChars = null,
        ?int $delay = null,
    ) {
        $this->root = new SearchRoot($name, $this->settings($defaults, $minChars, $delay), $types->all());
        $roots->open($this->root);

        $stylesheet->require(Stylesheet::SiteSearch);
        $script->require(ScriptModule::SiteSearch, $name, fn (): array => $description->of($this->root));
    }

    /**
     * @return list<string>
     */
    public function sectionTypes(): array
    {
        return array_keys($this->root->types);
    }

    public function render(): View
    {
        return view('meilifacets::components.search', ['contract' => Contract::version()]);
    }

    private function settings(SearchSettings $defaults, ?int $minChars, ?int $delay): SearchSettings
    {
        $settings = $minChars === null ? $defaults : $defaults->withMinChars($minChars);

        return $delay === null ? $settings : $settings->withDelay($delay);
    }
}
