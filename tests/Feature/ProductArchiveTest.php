<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Search\FilterExpression;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;
use WP_Query;
use WP_Term;

final class ProductArchiveTest extends TestCase
{
    private const string BUILT_IN = 'category';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The product listing is WooCommerce\'s.');
        }
    }

    #[Test]
    public function it_narrows_a_category_archive_to_its_term(): void
    {
        $category = $this->firstTermOf(ProductTaxonomy::Category->value);

        if (! $category instanceof WP_Term) {
            $this->markTestSkipped('This shop has no product category.');
        }

        $this->assertContains(
            $this->clauseOn(ProductTaxonomy::Category->value, $category->slug),
            $this->baseFilterOn([ProductTaxonomy::Category->value => $category->slug])
        );
    }

    #[Test]
    public function it_narrows_any_other_product_archive_to_its_term(): void
    {
        $browsable = $this->termsBrowsingTheCatalogue();

        $this->assertNotEmpty($browsable, 'This shop has no product taxonomy beyond its categories.');

        foreach ($browsable as $taxonomy => $slug) {
            $this->assertContains(
                $this->clauseOn($taxonomy, $slug),
                $this->baseFilterOn([$taxonomy => $slug]),
                $taxonomy.' leaves the archive showing the whole catalogue.'
            );
        }
    }

    /** A shop may file its products under the built-in categories: `is_tax()` answers false there, the queried object does not. */
    #[Test]
    public function it_narrows_an_archive_of_a_built_in_taxonomy_the_products_share(): void
    {
        $category = $this->firstTermOf(self::BUILT_IN);

        if (! $category instanceof WP_Term) {
            $this->markTestSkipped('This site has no post category.');
        }

        register_taxonomy_for_object_type(self::BUILT_IN, 'product');

        try {
            $this->assertContains(
                $this->clauseOn(self::BUILT_IN, $category->slug),
                $this->baseFilterOn(['category_name' => $category->slug])
            );
        } finally {
            unregister_taxonomy_for_object_type(self::BUILT_IN, 'product');
        }
    }

    #[Test]
    public function it_ignores_an_archive_outside_the_catalogue(): void
    {
        [$taxonomy, $slug] = $this->aTermOutsideTheCatalogue();
        $filter = $this->baseFilterOn([$taxonomy => $slug]);

        $this->assertNotContains($this->clauseOn($taxonomy, $slug), $filter);
        $this->assertSame($this->baseFilterOn([]), $filter);
    }

    /** The path already pins it: the facet would offer one value, its own, and the engine would count it for nothing. */
    #[Test]
    public function it_stops_offering_the_facet_of_the_taxonomy_the_path_pins(): void
    {
        $brand = $this->firstTermOf(ProductTaxonomy::Brand->value);

        if (! $brand instanceof WP_Term) {
            $this->markTestSkipped('This shop has no product brand.');
        }

        $archive = [ProductTaxonomy::Brand->value => $brand->slug];

        $this->assertNotContains(ProductTaxonomy::Brand->value, $this->taxonomiesOf($archive, 'facets'));
        $this->assertContains(ProductTaxonomy::Brand->value, $this->taxonomiesOf($archive, 'filters'));
        $this->assertContains(ProductTaxonomy::Brand->value, $this->taxonomiesOf([], 'facets'));
    }

    /** It offers the children of the term the path pins, which is what it is for. */
    #[Test]
    public function it_keeps_offering_the_category_facet_on_a_category_archive(): void
    {
        $category = $this->firstTermOf(ProductTaxonomy::Category->value);

        if (! $category instanceof WP_Term) {
            $this->markTestSkipped('This shop has no product category.');
        }

        $this->assertContains(
            ProductTaxonomy::Category->value,
            $this->taxonomiesOf([ProductTaxonomy::Category->value => $category->slug], 'facets')
        );
    }

    #[Test]
    public function it_narrows_nothing_off_an_archive(): void
    {
        $this->assertSame(
            ['post_type = "product"', 'post_status = "publish"', 'NOT facets.product_visibility = "exclude-from-catalog"'],
            $this->baseFilterOn([])
        );
    }

    /**
     * @return array<string, string> taxonomy to the slug of one of its terms
     */
    private function termsBrowsingTheCatalogue(): array
    {
        $browsable = [];

        foreach (get_object_taxonomies('product', 'objects') as $taxonomy) {
            $term = $taxonomy->public && $taxonomy->name !== ProductTaxonomy::Category->value
                ? $this->firstTermOf($taxonomy->name)
                : null;

            if ($term instanceof WP_Term) {
                $browsable[$taxonomy->name] = $term->slug;
            }
        }

        return $browsable;
    }

    /**
     * Custom, since `is_tax()` answers false on the built-in taxonomies, which have their own conditional tags.
     *
     * @return array{string, string}
     */
    private function aTermOutsideTheCatalogue(): array
    {
        foreach (get_taxonomies(['public' => true, '_builtin' => false], 'objects') as $taxonomy) {
            $term = is_object_in_taxonomy('product', $taxonomy->name) ? null : $this->firstTermOf($taxonomy->name);

            if ($term instanceof WP_Term) {
                return [$taxonomy->name, $term->slug];
            }
        }

        $this->markTestSkipped('This site has no custom taxonomy outside the catalogue.');
    }

    private function firstTermOf(string $taxonomy): ?WP_Term
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'number' => 1, 'hide_empty' => false]);

        return is_array($terms) && ($terms[0] ?? null) instanceof WP_Term ? $terms[0] : null;
    }

    private function clauseOn(string $taxonomy, string $slug): string
    {
        return FilterExpression::equals(DocumentField::Facets->path($taxonomy), $slug);
    }

    /**
     * @param  array<string, string>  $vars
     * @return list<string>
     */
    private function baseFilterOn(array $vars): array
    {
        return $this->onArchive($vars, static fn (ProductListing $listing): array => $listing->baseFilter());
    }

    /**
     * @param  array<string, string>  $vars
     * @param  'facets'|'filters'  $list
     * @return list<string>
     */
    private function taxonomiesOf(array $vars, string $list): array
    {
        return $this->onArchive($vars, static fn (ProductListing $listing): array => array_values(array_map(
            static fn (Placeable $filter): string => $filter instanceof Facet ? $filter->taxonomy : $filter->name,
            $listing->{$list}()
        )));
    }

    /**
     * Swapping the query is what makes `is_tax()` answer: it reads the global, never the URL.
     *
     * @template T
     *
     * @param  array<string, string>  $vars
     * @param  Closure(ProductListing): T  $read
     * @return T
     */
    private function onArchive(array $vars, Closure $read): mixed
    {
        global $wp_query;

        $archive = new WP_Query;
        $archive->parse_query($vars);
        $current = $wp_query;
        $wp_query = $archive;

        try {
            return $read(new ProductListing(new WooCommerceFacets(new NameOrder), new WooCommerceSorts));
        } finally {
            $wp_query = $current;
        }
    }
}
