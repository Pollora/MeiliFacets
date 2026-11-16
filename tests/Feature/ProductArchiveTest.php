<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FilterExpression;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\VariationTaxonomies;
use Modules\MeiliFacets\Listing\WooCommerceDefaultSort;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Tests\TestCase;
use WooCommerce;
use WP_Query;
use WP_Term;

trait ProductArchiveSteps
{
    private const string BUILT_IN = 'category';

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
     * @param  WP_Term|null  $browsed  the term the archive shows, when the database need not hold it
     * @return T
     */
    private function onArchive(array $vars, Closure $read, ?WP_Term $browsed = null): mixed
    {
        global $wp_query;

        $archive = new WP_Query;
        $archive->parse_query($vars);

        if ($browsed instanceof WP_Term) {
            $archive->queried_object = $browsed;
        }

        $current = $wp_query;
        $wp_query = $archive;

        try {
            return $read(new ProductListing(
                new WooCommerceFacets(new NameOrder),
                new WooCommerceSorts,
                $this->app->make(SearchableTypes::class),
                new VariationTaxonomies,
                new WooCommerceDefaultSort,
            ));
        } finally {
            $wp_query = $current;
        }
    }
}

uses(TestCase::class, ProductArchiveSteps::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('The product listing is WooCommerce\'s.');
    }
});

it('narrows a category archive to its term', function (): void {
    $category = $this->firstTermOf(ProductTaxonomy::Category->value);

    if (! $category instanceof WP_Term) {
        $this->markTestSkipped('This shop has no product category.');
    }

    expect($this->baseFilterOn([ProductTaxonomy::Category->value => $category->slug]))->toContain($this->clauseOn(ProductTaxonomy::Category->value, $category->slug));
});

it('narrows any other product archive to its term', function (): void {
    $browsable = $this->termsBrowsingTheCatalogue();

    $this->assertNotEmpty($browsable, 'This shop has no product taxonomy beyond its categories.');

    foreach ($browsable as $taxonomy => $slug) {
        $this->assertContains(
            $this->clauseOn($taxonomy, $slug),
            $this->baseFilterOn([$taxonomy => $slug]),
            $taxonomy.' leaves the archive showing the whole catalogue.'
        );
    }
});

// A shop may file its products under the built-in categories: `is_tax()` answers false there, the queried object does not.
it('narrows an archive of a built in taxonomy the products share', function (): void {
    $category = $this->firstTermOf(self::BUILT_IN);

    if (! $category instanceof WP_Term) {
        $this->markTestSkipped('This site has no post category.');
    }

    register_taxonomy_for_object_type(self::BUILT_IN, 'product');

    try {
        expect($this->baseFilterOn(['category_name' => $category->slug]))->toContain($this->clauseOn(self::BUILT_IN, $category->slug));
    } finally {
        unregister_taxonomy_for_object_type(self::BUILT_IN, 'product');
    }
});

it('ignores an archive outside the catalogue', function (): void {
    [$taxonomy, $slug] = $this->aTermOutsideTheCatalogue();
    $filter = $this->baseFilterOn([$taxonomy => $slug]);

    expect($filter)->not->toContain($this->clauseOn($taxonomy, $slug));
    expect($filter)->toBe($this->baseFilterOn([]));
});

// The path already pins it: the facet would offer one value, its own, and the engine would count it for nothing.
it('stops offering the facet of the taxonomy the path pins', function (): void {
    $brand = $this->firstTermOf(ProductTaxonomy::Brand->value);

    if (! $brand instanceof WP_Term) {
        $this->markTestSkipped('This shop has no product brand.');
    }

    $archive = [ProductTaxonomy::Brand->value => $brand->slug];

    expect($this->taxonomiesOf($archive, 'facets'))->not->toContain(ProductTaxonomy::Brand->value);
    expect($this->taxonomiesOf($archive, 'filters'))->toContain(ProductTaxonomy::Brand->value);
    expect($this->taxonomiesOf([], 'facets'))->toContain(ProductTaxonomy::Brand->value);
});

// It offers the children of the term the path pins, which is what it is for.
it('keeps offering the category facet on a category archive', function (): void {
    $category = $this->firstTermOf(ProductTaxonomy::Category->value);

    if (! $category instanceof WP_Term) {
        $this->markTestSkipped('This shop has no product category.');
    }

    expect($this->taxonomiesOf([ProductTaxonomy::Category->value => $category->slug], 'facets'))->toContain(ProductTaxonomy::Category->value);
});

// `/categorie-produit/visage?q=creme` searches the category, not the whole shop. The term lives in
// memory: nothing is written to the database, nor to the index.
it('keeps its term when the visitor searches', function (): void {
    $category = new WP_Term((object) ['term_id' => 0, 'taxonomy' => ProductTaxonomy::Category->value, 'slug' => 'visage']);

    expect($this->onArchive(
        [ProductTaxonomy::Category->value => $category->slug],
        static fn (ProductListing $listing): array => $listing->searchScope()->filter,
        $category,
    ))->toContain($this->clauseOn(ProductTaxonomy::Category->value, $category->slug));
});

it('narrows nothing off an archive', function (): void {
    expect($this->baseFilterOn([]))->toBe([
        'post_type = "product"',
        'post_status = "publish"',
        'NOT facets.product_visibility = "exclude-from-catalog"',
    ]);
});
