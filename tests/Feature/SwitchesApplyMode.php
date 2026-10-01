<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Listing\ProductListing;

/** A listing reads its mode once, when discovery builds it: switching the config rebuilds it the same way. */
trait SwitchesApplyMode
{
    private function useApplyMode(?string $mode): void
    {
        config(['meilifacets.apply_mode' => $mode]);
        $this->app->make(ListingRegistry::class)->add($this->app->make(ProductListing::class));
    }
}
