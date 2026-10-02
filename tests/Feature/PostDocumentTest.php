<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Indexing\AnonymousVisitor;
use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Modules\MeiliFacets\Indexing\PostDocument;
use Modules\MeiliFacets\Indexing\PostText;
use Modules\MeiliFacets\Indexing\ProductPriceProjector;
use Modules\MeiliFacets\Indexing\ShopTaxLocation;
use Modules\MeiliFacets\Indexing\TermAncestry;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Post;

/** The fields the module adds to a MeiliScout document, as the single hook writes them. */
final class PostDocumentTest extends TestCase
{
    use PinsIndexedPostTypes;

    private const array CARD = ['title' => 'Routine'];

    private const string ATTRIBUTE = 'pa_test_volume';

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinIndexedPostTypes();
        register_taxonomy(self::ATTRIBUTE, 'post', ['public' => false]);
    }

    protected function tearDown(): void
    {
        unregister_taxonomy(self::ATTRIBUTE);
        $this->unpinIndexedPostTypes();

        parent::tearDown();
    }

    #[Test]
    public function it_files_term_names_under_their_taxonomy(): void
    {
        $labels = $this->completed([
            $this->term(1, 'Visage', 'category'),
            $this->term(2, 'Laits &amp; crèmes', 'category'),
        ])[DocumentField::Labels->value];

        $this->assertSame(['category' => ['Visage', 'Laits & crèmes']], $labels);
    }

    /** A WooCommerce attribute without archives is not viewable, yet its values are what a visitor types. */
    #[Test]
    public function it_labels_a_taxonomy_without_archives(): void
    {
        $labels = $this->completed([$this->term(1, '100ml', self::ATTRIBUTE)])[DocumentField::Labels->value];

        $this->assertSame([self::ATTRIBUTE => ['100ml']], $labels);
    }

    /** `exclude-from-search` is a term of `product_visibility`: labelled, it would be found by its own name. */
    #[Test]
    public function it_labels_no_term_of_a_technical_taxonomy_yet_filters_on_it(): void
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

    #[Test]
    public function it_writes_the_card_variants_as_a_list(): void
    {
        $card = ['title' => 'Routine', CardField::Variants->value => [1 => ['price' => 26], 3 => ['price' => 39]]];

        $document = $this->postDocument($card)->complete([], $this->article());

        $this->assertSame([['price' => 26], ['price' => 39]], $document[DocumentField::Card->value][CardField::Variants->value]);
    }

    /** A frozen snapshot: every field, every value and the key order, on a post that lives only in memory. */
    #[Test]
    public function it_writes_this_exact_document(): void
    {
        $document = $this->postDocument()->complete(
            ['ID' => 7, DocumentField::Terms->value => [$this->term(1, 'Soins &amp; rituels', 'category')]],
            $this->article()
        );

        $this->assertSame([
            'ID' => 7,
            'terms' => [['term_id' => 1, 'name' => 'Soins &amp; rituels', 'slug' => 'soins-rituels', 'taxonomy' => 'category', 'parent' => 0]],
            'facets' => ['category' => ['soins-rituels']],
            'labels' => ['category' => ['Soins & rituels']],
            'excerpt' => 'Doux & frais',
            'content' => 'Routine',
            'card' => ['title' => 'Routine'],
        ], $document);
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     * @return array<string, mixed>
     */
    private function completed(array $terms): array
    {
        return $this->postDocument()->complete([DocumentField::Terms->value => $terms], $this->article());
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function postDocument(array $card = self::CARD): PostDocument
    {
        return new PostDocument(
            new TermAncestry(new WordPressTermHierarchy),
            new IndexedTaxonomies(new IndexedPostTypes),
            new PostText,
            $this->card($card),
            new ProductPriceProjector,
            new ShopTaxLocation,
            new AnonymousVisitor
        );
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function card(array $card): CardProjector
    {
        return new readonly class($card) implements CardProjector
        {
            /**
             * @param  array<string, mixed>  $card
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
}
