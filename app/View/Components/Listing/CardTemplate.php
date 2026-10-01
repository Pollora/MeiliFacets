<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Modules\MeiliFacets\Enums\HeadingLevel;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardBinding;

final class CardTemplate extends Card
{
    public function __construct(HeadingLevel|string $heading = HeadingLevel::H3)
    {
        $this->prepare(CardBinding::template(), $heading, ImagePriority::Lazy);
    }
}
