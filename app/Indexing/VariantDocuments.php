<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\DocumentKind;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Listing\CardVariant;
use Modules\MeiliFacets\Search\FilterExpression;

/** One document per variant of a product: the product's document, under the variant's terms, price and stock. */
final readonly class VariantDocuments
{
    private const string ID_SEPARATOR = '-';

    /**
     * @param  array<string, mixed>  $product
     * @return list<array<string, mixed>>
     */
    public function of(array $product): array
    {
        $productId = $product[DocumentField::Id->value] ?? null;

        if (! is_int($productId)) {
            return [];
        }

        $documents = [];

        foreach ($this->variantsOf($product) as $position => $variant) {
            $documents[] = $this->document($product, $productId, $position, $variant);
        }

        return $documents;
    }

    /**
     * A post ID is an integer; anything else matches no document.
     *
     * @param  non-empty-list<int|string>  $productIds
     */
    public function filterOf(array $productIds): string
    {
        return FilterExpression::oneOf(DocumentField::ParentId->value, array_map(intval(...), $productIds));
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function document(array $product, int $productId, int $position, CardVariant $variant): array
    {
        return [
            ...$product,
            DocumentField::Id->value => $productId.self::ID_SEPARATOR.$position,
            DocumentField::ParentId->value => $productId,
            DocumentField::Kind->value => DocumentKind::Variant->value,
            DocumentField::InStock->value => (int) $variant->inStock,
            DocumentField::Facets->value => array_replace(
                $this->arrayAt($product, DocumentField::Facets),
                $variant->facets
            ),
            DocumentField::Price->value => [
                ...$this->arrayAt($product, DocumentField::Price),
                PriceField::Min->value => $variant->price,
                PriceField::Max->value => $variant->price,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return list<CardVariant>
     */
    private function variantsOf(array $product): array
    {
        $stored = $this->arrayAt($product, DocumentField::Card)[CardField::Variants->value] ?? [];

        if (! is_array($stored) || ! array_is_list($stored)) {
            return [];
        }

        return array_values(array_filter(array_map(CardVariant::read(...), $stored)));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function arrayAt(array $document, DocumentField $field): array
    {
        $value = $document[$field->value] ?? [];

        return is_array($value) ? $value : [];
    }
}
