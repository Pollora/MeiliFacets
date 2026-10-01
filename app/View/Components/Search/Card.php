<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Search;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardFieldElement;
use Modules\MeiliFacets\View\CardHooks;
use Modules\MeiliFacets\View\Components\ContractComponent;

final class Card extends ContractComponent
{
    public CardFieldElement $link;

    public CardFieldElement $image;

    public CardFieldElement $title;

    public CardFieldElement $summary;

    public CardFieldElement $price;

    public function __construct()
    {
        $hooks = new CardHooks(CardBinding::template());

        $this->link = $hooks->link();
        $this->image = $hooks->thumbnail();
        $this->title = $hooks->title();
        $this->summary = $hooks->summary();
        $this->price = $hooks->price();
    }

    public function render(): View
    {
        return view('meilifacets::components.search.card');
    }
}
