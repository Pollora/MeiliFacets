<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Indexing\AnonymousVisitor;
use Modules\MeiliFacets\Indexing\FacetProjection;
use Modules\MeiliFacets\Indexing\LabelProjection;
use Modules\MeiliFacets\Indexing\MeiliScoutBridge;
use Modules\MeiliFacets\Indexing\PostDocument;
use Modules\MeiliFacets\Indexing\PostText;
use Modules\MeiliFacets\Indexing\ProductPriceProjector;
use Modules\MeiliFacets\Indexing\ShopTaxLocation;
use Modules\MeiliFacets\Indexing\TermAncestry;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use PHPUnit\Framework\Attributes\Test;
use Pollora\MeiliScout\Indexables\PostIndexable;
use Tests\TestCase;
use WC_Product;
use WooCommerce;
use WP_Post;

/** The fields the module adds to a MeiliScout document, as the single hook writes them. */
final class PostDocumentTest extends TestCase
{
    private const array CARD = ['title' => 'Routine'];

    #[Test]
    public function it_files_term_names_under_their_taxonomy(): void
    {
        $labels = $this->completed([
            $this->term(1, 'Visage', 'category'),
            $this->term(2, 'Laits &amp; crèmes', 'category'),
            $this->term(3, 'Article', 'contenu'),
        ])[DocumentField::Labels->value];

        $this->assertSame(['category' => ['Visage', 'Laits & crèmes'], 'contenu' => ['Article']], $labels);
    }

    /** `exclude-from-search` is a term of `product_visibility`: labelled, it would be found by its own name. */
    #[Test]
    public function it_labels_no_term_of_a_taxonomy_wordpress_does_not_show_yet_filters_on_it(): void
    {
        $document = $this->completed([$this->term(1, 'exclude-from-search', 'product_visibility')]);

        $this->assertSame([], $document[DocumentField::Labels->value]);
        $this->assertSame(['product_visibility' => ['exclude-from-search']], $document[DocumentField::Facets->value]);
    }

    #[Test]
    public function it_facets_and_labels_nothing_on_a_document_without_terms(): void
    {
        $document = $this->postDocument()->complete(['ID' => 1], $this->article());

        $this->assertSame([], $document[DocumentField::Facets->value]);
        $this->assertSame([], $document[DocumentField::Labels->value]);
    }

    /** Each field used to come from its own filter: an unreadable `terms` never stopped the others. */
    #[Test]
    public function it_still_projects_the_rest_when_the_terms_are_unreadable(): void
    {
        $document = $this->postDocument()->complete([DocumentField::Terms->value => 'broken'], $this->article());

        $this->assertArrayNotHasKey(DocumentField::Facets->value, $document);
        $this->assertArrayNotHasKey(DocumentField::Labels->value, $document);
        $this->assertSame('Doux & frais', $document[DocumentField::Excerpt->value]);
        $this->assertSame('Routine', $document[DocumentField::Content->value]);
        $this->assertSame(self::CARD, $document[DocumentField::Card->value]);
    }

    #[Test]
    public function it_writes_the_excerpt_and_the_content_as_plain_text(): void
    {
        $document = $this->completed([]);

        $this->assertSame('Doux & frais', $document[DocumentField::Excerpt->value]);
        $this->assertSame('Routine', $document[DocumentField::Content->value]);
    }

    #[Test]
    public function it_leaves_a_card_without_a_price_off_the_price_field(): void
    {
        $this->assertArrayNotHasKey(DocumentField::Price->value, $this->completed([]));
    }

    /** What the six filters of the bridge wrote before they became one, key for key and in the same order. */
    #[Test]
    public function it_writes_the_same_document_through_the_single_hook(): void
    {
        $post = $this->aPricedProductWithTerms();
        $original = get_object_vars($post);
        $original[DocumentField::Terms->value] = $this->termsOf($post);

        $document = $this->app->make(MeiliScoutBridge::class)->addModuleFields($original, $post);
        $expanded = $this->app->make(TermAncestry::class)->expand($original[DocumentField::Terms->value]);

        $this->assertSame(
            [...array_keys($original), 'facets', 'labels', 'excerpt', 'content', 'card', 'price'],
            array_keys($document)
        );
        $this->assertSame(FacetProjection::fromTerms($expanded), $document[DocumentField::Facets->value]);
        $this->assertSame(new PostText()->content($post), $document[DocumentField::Content->value]);
        $this->assertSame($this->app->make(CardProjector::class)->project($post), $document[DocumentField::Card->value]);
        $this->assertSame(new ProductPriceProjector()->project($post), $document[DocumentField::Price->value]);
        $this->assertSame(
            LabelProjection::fromTerms(array_values(array_filter(
                $expanded,
                static fn (array $term): bool => is_taxonomy_viewable($term['taxonomy'])
            ))),
            $document[DocumentField::Labels->value]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     * @return array<string, mixed>
     */
    private function completed(array $terms): array
    {
        return $this->postDocument()->complete([DocumentField::Terms->value => $terms], $this->article());
    }

    private function postDocument(): PostDocument
    {
        return new PostDocument(
            new TermAncestry(new WordPressTermHierarchy),
            new PostText,
            $this->card(),
            new ProductPriceProjector,
            new ShopTaxLocation,
            new AnonymousVisitor
        );
    }

    private function card(): CardProjector
    {
        return new readonly class(self::CARD) implements CardProjector
        {
            /**
             * @param  array<string, string>  $card
             */
            public function __construct(private array $card) {}

            /**
             * @return array<string, mixed>
             */
            public function project(WP_Post $post): array
            {
                return $this->card;
            }
        };
    }

    private function article(): WP_Post
    {
        return new WP_Post((object) [
            'post_type' => 'post',
            'post_content' => '<!-- wp:spacer --><div class="wp-block-spacer"></div><!-- /wp:spacer --><p>Routine</p>',
            'post_excerpt' => '<p>Doux &amp; <em>frais</em></p>',
        ]);
    }

    /**
     * A root term: nothing is read from the database to expand it.
     *
     * @return array<string, mixed>
     */
    private function term(int $id, string $name, string $taxonomy): array
    {
        return ['term_id' => $id, 'name' => $name, 'slug' => sanitize_title($name), 'taxonomy' => $taxonomy, 'parent' => 0];
    }

    private function aPricedProductWithTerms(): WP_Post
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The price is WooCommerce\'s.');
        }

        foreach (wc_get_products(['status' => 'publish', 'limit' => -1]) as $product) {
            if ($product instanceof WC_Product && $product->get_price() !== '' && $product->get_category_ids() !== []) {
                return get_post($product->get_id());
            }
        }

        $this->markTestSkipped('The catalogue has no published, priced and filed product.');
    }

    /**
     * The terms MeiliScout attaches, read the way `PostIndexable` reads them.
     *
     * @return list<array<string, mixed>>
     */
    private function termsOf(WP_Post $post): array
    {
        $document = new PostIndexable()->formatForIndexing($post);

        return $document[DocumentField::Terms->value];
    }
}
