<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Support\UniqueList;

/** Title, then what names a product, then every other label, then the prose. */
final readonly class DefaultSearchableAttributes implements SearchableAttributes
{
    public function __construct(
        private IndexedTaxonomies $taxonomies,
        private WooCommerceProductFields $productFields,
    ) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return UniqueList::merge(
            [DocumentField::Title->value],
            $this->productFields->all(),
            $this->labelFields(),
            [DocumentField::Excerpt->value, DocumentField::Content->value]
        );
    }

    /**
     * @return list<string>
     */
    private function labelFields(): array
    {
        return array_map(DocumentField::Labels->path(...), $this->taxonomies->labelled());
    }
}
