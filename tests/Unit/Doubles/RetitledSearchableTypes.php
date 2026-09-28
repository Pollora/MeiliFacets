<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;

/** The decorator `configuration.md` shows, as a project would write it. */
final readonly class RetitledSearchableTypes implements SearchableTypes
{
    private const string POST_TYPE = 'post';

    public function __construct(private WooCommerceSearchableTypes $default) {}

    public function all(): array
    {
        $types = $this->default->all();

        if (isset($types[self::POST_TYPE])) {
            $types[self::POST_TYPE] = $types[self::POST_TYPE]
                ->withHeading(__('News'))
                ->withSeeAllLabel(__('All the news'));
        }

        return $types;
    }
}
