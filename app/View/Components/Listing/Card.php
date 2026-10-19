<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardFieldElement;
use Modules\MeiliFacets\View\CardHooks;
use Modules\MeiliFacets\View\Components\ContractComponent;

class Card extends ContractComponent
{
    public CardFieldElement $link;

    public CardFieldElement $image;

    public CardFieldElement $title;

    public CardFieldElement $price;

    public function __construct(
        public CardBinding $binding,
        public ImagePriority $priority = ImagePriority::Lazy,
    ) {
        $hooks = new CardHooks($binding);

        $this->link = $hooks->link();
        $this->image = $hooks->image($priority);
        $this->title = $hooks->title();
        $this->price = $hooks->price();
    }

    public function render(): View
    {
        return view('meilifacets::components.listing.card');
    }
}
