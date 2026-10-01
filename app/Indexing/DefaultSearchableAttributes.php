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
            DocumentField::Labels->paths($this->taxonomies->labelled()),
            [DocumentField::Excerpt->value, DocumentField::Content->value]
        );
    }
}
