<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Discovery;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Listing\ListingUnavailable;
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
            } catch (ListingUnavailable) {
                continue;
            } catch (Throwable $failure) {
                $this->report($failure);

                continue;
            }
        }
    }

    /**
     * Unreported, a project binding that throws surfaces as "no listing is
     * declared". A handler that throws in turn must not take the page down.
     */
    private function report(Throwable $failure): void
    {
        try {
            report($failure);
        } catch (Throwable) {
        }
    }

    public function getIdentifier(): string
    {
        return 'meilifacets_listings';
    }
}
