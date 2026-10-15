<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Illuminate\View\Component;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardFieldElement;
use Modules\MeiliFacets\View\CardHooks;

/** A card a listing names through `cardComponent()`. */
final class ProjectListingCard extends Component
{
    public const string ALIAS = 'probe-listing-card';

    public CardFieldElement $title;

    public function __construct(CardBinding $binding, public ImagePriority $priority)
    {
        $this->title = new CardHooks($binding)->title();
    }

    public function render(): string
    {
        return '<p class="probeListingCard" data-priority="{{ $priority->loading() }}" {{ $title->attributes }}>'
            .'{{ $title }}</p>';
    }
}
