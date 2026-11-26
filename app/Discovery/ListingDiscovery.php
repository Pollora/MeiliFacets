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
            // Not autoloaded here: the class may extend one of a plugin WordPress has not loaded yet.
            $listingClass = is_array($item) ? $item['class'] ?? null : null;

            if (is_string($listingClass)) {
                $this->registry->addDeclaration($listingClass);
            }
        }
    }

    public function getIdentifier(): string
    {
        return 'meilifacets_listings';
    }
}
