<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;

/** The decorator `configuration.md` shows, as a project would write it. */
final readonly class ExcerptFirstSearchableAttributes implements SearchableAttributes
{
    private const string EXCERPT = 'excerpt';

    private const int RIGHT_AFTER_THE_TITLE = 1;

    public function __construct(private DefaultSearchableAttributes $default) {}

    public function all(): array
    {
        $fields = array_values(array_diff($this->default->all(), [self::EXCERPT]));
        array_splice($fields, self::RIGHT_AFTER_THE_TITLE, 0, [self::EXCERPT]);

        return $fields;
    }
}
