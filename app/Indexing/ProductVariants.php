<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Illuminate\Container\Attributes\Config;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\TermField;
use Modules\MeiliFacets\Enums\VariationPrices;
use Modules\MeiliFacets\Listing\CardVariant;
use Modules\MeiliFacets\Support\WooCommerce;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Post;
use WP_Term;

/**
 * The ways a variable product is sold, read off WooCommerce for the product's card.
 *
 * @phpstan-import-type StoredVariant from CardVariant
 */
final readonly class ProductVariants
{
    private const string AS_OBJECTS = 'objects';

    /** The variation's own image: in the default context WooCommerce lends it the parent's. */
    private const string EDIT_CONTEXT = 'edit';

    public function __construct(
        private VariantFields $variantFields,
        private TermHierarchy $hierarchy,
        #[Config('meilifacets.card.image_size', WooCommerceCardProjector::DEFAULT_IMAGE_SIZE)]
        private string $imageSize,
    ) {}

    /**
     * @return list<StoredVariant>
     */
    public function project(WP_Post $post): array
    {
        if (! WooCommerce::isActive()) {
            return [];
        }

        $product = wc_get_product($post);

        return $product instanceof WC_Product_Variable ? $this->variants($product) : [];
    }

    /**
     * @return list<StoredVariant>
     */
    private function variants(WC_Product_Variable $product): array
    {
        $prices = $product->get_variation_prices(for_display: true)[VariationPrices::Active->value];
        $variations = $product->get_available_variations(self::AS_OBJECTS);
        $this->primeOwnImages($variations);

        $variants = [];

        foreach ($variations as $variation) {
            $price = $prices[$variation->get_id()] ?? null;

            if ($price !== null) {
                $variants[] = $this->variant($variation, (float) $price)->toArray();
            }
        }

        return $variants;
    }

    private function variant(WC_Product_Variation $variation, float $price): CardVariant
    {
        return new CardVariant(
            $this->facetsOf($variation),
            $price,
            $this->fieldsOf($variation),
            inStock: $variation->is_in_stock(),
            onSale: $variation->is_on_sale(),
        );
    }

    /**
     * A variation sold for "any" value holds an empty slug: it carries no term for that attribute.
     *
     * @return array<string, list<string>>
     */
    private function facetsOf(WC_Product_Variation $variation): array
    {
        $facets = [];

        foreach ($variation->get_attributes() as $taxonomy => $slug) {
            if ($slug !== '' && taxonomy_is_product_attribute($taxonomy)) {
                $facets[$taxonomy] = [$slug, ...$this->ancestorSlugsOf($slug, $taxonomy)];
            }
        }

        return $facets;
    }

    /**
     * A filter on a parent term matches the product's document, which carries its ancestors: the variant's must too.
     *
     * @return list<string>
     */
    private function ancestorSlugsOf(string $slug, string $taxonomy): array
    {
        $term = is_taxonomy_hierarchical($taxonomy) ? get_term_by('slug', $slug, $taxonomy) : false;

        if (! $term instanceof WP_Term) {
            return [];
        }

        return array_column($this->hierarchy->ancestorsOf($term->term_id, $taxonomy), TermField::Slug->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(WC_Product_Variation $variation): array
    {
        $fields = $this->nonEmpty([
            CardField::Price->value => (string) $variation->get_price_html(),
            CardField::Url->value => (string) $variation->get_permalink(),
            ...$this->variantFields->project($variation),
        ]);

        return [...$this->ownImage($variation), ...$fields];
    }

    /**
     * @return array<string, string|int>
     */
    private function ownImage(WC_Product_Variation $variation): array
    {
        return ImageFields::whole($this->ownImageId($variation), $this->imageSize);
    }

    private function ownImageId(WC_Product_Variation $variation): int
    {
        return (int) $variation->get_image_id(self::EDIT_CONTEXT);
    }

    /**
     * @param  list<WC_Product_Variation>  $variations
     */
    private function primeOwnImages(array $variations): void
    {
        $imageIds = array_filter(array_map($this->ownImageId(...), $variations));

        _prime_post_caches($imageIds, update_term_cache: false);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function nonEmpty(array $fields): array
    {
        return array_filter($fields, static fn (mixed $field): bool => $field !== '' && $field !== []);
    }
}
