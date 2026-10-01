<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\SiteSearch;

use Closure;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Indexing\WooCommerceProductFields;
use Modules\MeiliFacets\Search\VisibleProducts;

final class WooCommerceSearchableTypes implements SearchableTypes
{
    private ?SearchableType $products = null;

    /**
     * @param  Closure(): bool  $pluginIsActive  asked on every read, not when the search is wired
     */
    public function __construct(
        private readonly WordPressSearchableTypes $wordPress,
        private readonly SearchablePostTypes $postTypes,
        private readonly SearchableTypeFactory $factory,
        private readonly WooCommerceProductFields $productFields,
        private readonly Closure $pluginIsActive,
    ) {}

    public function all(): array
    {
        if ($this->searchesProducts()) {
            return [VisibleProducts::POST_TYPE => $this->products(), ...$this->wordPress->all()];
        }

        return $this->wordPress->all();
    }

    private function searchesProducts(): bool
    {
        return ($this->pluginIsActive)() && $this->postTypes->contains(VisibleProducts::POST_TYPE);
    }

    private function products(): SearchableType
    {
        return $this->products ??= $this->factory->make(
            VisibleProducts::POST_TYPE,
            VisibleProducts::inSearch(),
            [DocumentField::Title->value, ...$this->productFields->all()],
        );
    }
}
