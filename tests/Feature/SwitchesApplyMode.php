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
        $current = $this->app->make(ListingRegistry::class);
        $registry = new ListingRegistry($this->app);
        $registry->add($this->app->make(ProductListing::class));

        foreach (array_diff($current->names(), [ProductListing::NAME]) as $name) {
            $registry->add($current->named($name));
        }

        $this->app->instance(ListingRegistry::class, $registry);
        $this->app->forgetScopedInstances();
    }
}
