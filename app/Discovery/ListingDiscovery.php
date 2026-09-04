<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Discovery;

use Modules\MeiliFacets\Contracts\Listing;
use Pollora\Discovery\Domain\Contracts\DiscoveryInterface;
use Pollora\Discovery\Domain\Contracts\DiscoveryLocationInterface;
use Pollora\Discovery\Domain\Contracts\ReflectionCacheInterface;
use Pollora\Discovery\Domain\Services\IsDiscovery;
use Spatie\StructureDiscoverer\Data\DiscoveredClass;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Throwable;

final class ListingDiscovery implements DiscoveryInterface
{
    use IsDiscovery;

    public function __construct(private readonly ListingRegistry $registry) {}

    public function discover(
        DiscoveryLocationInterface $location,
        DiscoveredStructure $structure,
        ?ReflectionCacheInterface $reflectionCache = null
    ): void {
        if (! $structure instanceof DiscoveredClass || $structure->isAbstract) {
            return;
        }

        $className = $structure->namespace.'\\'.$structure->name;

        if (is_a($className, Listing::class, true)) {
            $this->getItems()->add($location, ['class' => $className]);
        }
    }

    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            try {
                $this->registry->add(app($item['class']));
            } catch (Throwable) {
                // A listing whose dependencies cannot be built is skipped rather
                // than taking the whole page down.
                continue;
            }
        }
    }

    public function getIdentifier(): string
    {
        return 'meilifacets_listings';
    }
}
