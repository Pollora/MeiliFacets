<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Search\VisibleProducts;

final class WordPressSearchableTypes implements SearchableTypes
{
    private ?array $types = null;

    public function __construct(
        private readonly SearchablePostTypes $postTypes,
        private readonly SearchableTypeFactory $factory,
    ) {}

    public function all(): array
    {
        return $this->types ??= $this->derive();
    }

    private function derive(): array
    {
        $postTypes = array_values(array_diff($this->postTypes->all(), [VisibleProducts::POST_TYPE]));

        return array_combine($postTypes, array_map($this->factory->forPostType(...), $postTypes));
    }
}
