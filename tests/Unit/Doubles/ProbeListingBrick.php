<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\Components\ListingComponent;

/** A brick that only records the listing it resolved: the root may render its slot or not. */
final class ProbeListingBrick extends ListingComponent
{
    public const string ALIAS = 'probe-listing-brick';

    /** @var list<string> */
    public static array $resolved = [];

    public function __construct(CurrentListing $listings, string $name = '')
    {
        parent::__construct($listings, $name);

        self::$resolved[] = $this->listing->name();
    }

    public function render(): string
    {
        return '';
    }
}
