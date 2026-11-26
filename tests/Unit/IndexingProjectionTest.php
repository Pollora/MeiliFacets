<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Indexing\DefaultCardProjector;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;
use Modules\MeiliFacets\Indexing\DeferredCardProjector;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Indexing\ImageFields;
use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Modules\MeiliFacets\Indexing\MeiliScoutBridge;
use Modules\MeiliFacets\Indexing\PostDocument;
use Modules\MeiliFacets\Indexing\PostText;
use Modules\MeiliFacets\Indexing\SummaryCardProjector;
use Modules\MeiliFacets\Indexing\TermAncestry;
use Modules\MeiliFacets\Indexing\WooCommerce\AnonymousVisitor;
use Modules\MeiliFacets\Indexing\WooCommerce\EmptyVariantFields;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductPopularity;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductPriceProjector;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductStock;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductVariants;
use Modules\MeiliFacets\Indexing\WooCommerce\ShopFields;
use Modules\MeiliFacets\Indexing\WooCommerce\ShopTaxLocation;
use Modules\MeiliFacets\Indexing\WooCommerce\VariantDocuments;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceProductFields;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pollora\MeiliScout\Contracts\Indexable;
use Pollora\MeiliScout\Indexables\PostIndexable;
use WP_Post;
use WP_Term;

/** Reads WordPress helpers, so each test gets a process of its own: see `Doubles\WordPress`. */
final class IndexingProjectionTest extends TestCase
{
    protected function setUp(): void
    {
        WordPress::boot();
    }

    #[Test]
    public function it_reads_the_text_of_a_post_without_markup_or_shortcodes(): void
    {
        WordPress::fake('strip_shortcodes', static fn (string $markup): string => preg_replace('/\[[^\]]+\]/', '', $markup));
        $post = new WP_Post(['post_content' => '<p>Soft &amp; warm [gallery id=1]</p><script>x()</script>', 'post_excerpt' => '<b>Short</b>']);
        $text = new PostText;

        $this->assertSame('Soft & warm', $text->content($post));
        $this->assertSame('Short', $text->excerpt($post));
    }

    #[Test]
    public function it_shows_nothing_of_a_password_protected_post(): void
    {
        $post = new WP_Post(['post_content' => 'secret', 'post_excerpt' => 'secret', 'post_password' => 'pw']);
        $text = new PostText;

        $this->assertSame('', $text->content($post));
        $this->assertSame('', $text->excerpt($post));
        $this->assertSame('', $text->summary($post, 10));
    }

    #[Test]
    public function it_summarises_a_post_by_its_own_excerpt_before_its_opening_words(): void
    {
        WordPress::fake('strip_shortcodes', static fn (string $markup): string => $markup);
        WordPress::fake('wp_trim_words', static fn (string $text, int $words, string $more): string => implode(' ', array_slice(explode(' ', trim($text)), 0, $words)).$more);
        $text = new PostText;

        $this->assertSame('Own excerpt', $text->summary(new WP_Post(['post_excerpt' => 'Own excerpt', 'post_content' => 'ignored']), 3));
        $this->assertSame('One two three…', $text->summary(new WP_Post(['post_content' => '<p>One two three four five</p>']), 3));
    }

    #[Test]
    public function it_projects_the_title_url_and_image_of_a_post_into_a_card(): void
    {
        WordPress::fake('get_the_title', 'Tom &amp; Jerry');
        WordPress::fake('get_permalink', 'https://example.test/tom/');
        WordPress::fake('get_post_thumbnail_id', 0);

        $card = new DefaultCardProjector('medium')->project(new WP_Post);

        $this->assertSame(['title' => 'Tom & Jerry', 'url' => 'https://example.test/tom/'], $this->only($card, ['title', 'url']));
        $this->assertArrayNotHasKey('image_url', $card);
    }

    #[Test]
    public function it_describes_an_image_with_its_candidates_and_the_auto_sizes_the_theme_keeps(): void
    {
        $this->fakeAttachment(7);

        $image = ImageFields::of(7, 'medium');

        $this->assertSame('https://example.test/a.jpg', $image['image_url']);
        $this->assertSame('A "cat"', $image['image_alt']);
        $this->assertSame(300, $image['image_width']);
        $this->assertSame(200, $image['image_height']);
        $this->assertSame('a-300.jpg 300w', $image['image_srcset']);
        $this->assertSame('auto, (max-width: 300px) 100vw', $image['image_sizes']);
    }

    #[Test]
    public function it_leaves_the_sizes_alone_when_a_theme_turns_auto_sizes_off(): void
    {
        $this->fakeAttachment(7);
        WordPress::fake('apply_filters', static fn (string $hook, mixed $value): mixed => $hook === 'wp_img_tag_add_auto_sizes' ? false : $value);

        $this->assertSame('(max-width: 300px) 100vw', ImageFields::of(7, 'medium')['image_sizes']);
    }

    #[Test]
    public function it_gives_no_candidates_when_wordpress_has_no_srcset_for_the_image(): void
    {
        $this->fakeAttachment(7);
        WordPress::fake('wp_get_attachment_image_srcset', false);

        $image = ImageFields::of(7, 'medium');

        $this->assertArrayNotHasKey('image_srcset', $image);
        $this->assertArrayNotHasKey('image_sizes', $image);
    }

    #[Test]
    public function it_describes_no_image_for_a_missing_attachment(): void
    {
        WordPress::fake('wp_get_attachment_image_src', false);

        $this->assertSame([], ImageFields::of(0, 'medium'));
        $this->assertSame([], ImageFields::of(9, 'medium'));
        $this->assertSame([], ImageFields::whole(9, 'medium'));
    }

    #[Test]
    public function it_writes_every_image_field_so_a_variant_never_inherits_its_parents_image(): void
    {
        $this->fakeAttachment(7);

        $whole = ImageFields::whole(7, 'medium');

        $this->assertCount(6, $whole);
        $this->assertSame('https://example.test/a.jpg', $whole['image_url']);
        $this->assertSame('', ImageFields::withWholeImage(['image_url' => 'u'])['image_alt']);
        $this->assertSame(['title' => 'T'], ImageFields::withWholeImage(['title' => 'T']));
    }

    #[Test]
    public function it_adds_a_summary_to_the_card_when_the_post_has_one(): void
    {
        WordPress::fake('strip_shortcodes', static fn (string $markup): string => $markup);
        WordPress::fake('wp_trim_words', static fn (string $text): string => $text);
        $inner = new class implements CardProjector
        {
            public function project(WP_Post $post): array
            {
                return ['title' => 'T'];
            }
        };
        $card = new SummaryCardProjector($inner, new PostText);

        $this->assertSame(['title' => 'T', 'summary' => 'Hello'], $card->project(new WP_Post(['post_excerpt' => 'Hello'])));
        $this->assertSame(['title' => 'T'], $card->project(new WP_Post(['post_content' => ''])));
    }

    #[Test]
    public function it_projects_with_the_projector_that_fits_the_plugin_state(): void
    {
        $with = $this->constantProjector(['via' => 'plugin']);
        $without = $this->constantProjector(['via' => 'core']);

        $this->assertSame(['via' => 'plugin'], new DeferredCardProjector(static fn (): bool => true, $with, $without)->project(new WP_Post));
        $this->assertSame(['via' => 'core'], new DeferredCardProjector(static fn (): bool => false, $with, $without)->project(new WP_Post));
    }

    #[Test]
    public function it_lists_the_ancestors_of_a_term_with_the_name_that_makes_them_findable(): void
    {
        WordPress::fake('get_ancestors', [4, 9, 12]);
        WordPress::fake('get_term', static fn (int $id): WP_Term|false => $id === 9 ? false : new WP_Term([
            'term_id' => $id, 'name' => "Term {$id}", 'slug' => "term-{$id}", 'taxonomy' => 'product_cat', 'term_taxonomy_id' => $id + 100, 'parent' => 0,
        ]));

        $ancestors = new WordPressTermHierarchy()->ancestorsOf(20, 'product_cat');

        $this->assertSame(['term-4', 'term-12'], array_column($ancestors, 'slug'));
        $this->assertSame(['term_id' => 4, 'name' => 'Term 4', 'slug' => 'term-4', 'taxonomy' => 'product_cat', 'term_taxonomy_id' => 104, 'parent' => 0], $ancestors[0]);
    }

    #[Test]
    public function it_lists_the_slugs_of_the_children_of_a_term(): void
    {
        WordPress::fake('get_terms', [new WP_Term(['slug' => 'shoes']), 'noise', new WP_Term(['slug' => 'boots'])]);

        $this->assertSame(['shoes', 'boots'], new WordPressTermHierarchy()->childrenOf(3, 'product_cat'));

        WordPress::fake('get_terms', false);
        $this->assertSame([], new WordPressTermHierarchy()->childrenOf(3, 'product_cat'));
    }

    #[Test]
    public function it_reads_the_indexed_post_types_from_the_settings_and_their_taxonomies_once_per_change(): void
    {
        WordPress::fake('get_option', ['post', 'product']);
        WordPress::fake('get_object_taxonomies', static fn (string $type): array => $type === 'post' ? ['category', 'post_tag'] : ['product_cat', 'product_visibility', 'category']);
        $taxonomies = new IndexedTaxonomies(new IndexedPostTypes);

        $this->assertSame(['post', 'product'], new IndexedPostTypes()->all());
        $this->assertSame(['category', 'post_tag', 'product_cat', 'product_visibility'], $taxonomies->all());
        $this->assertSame(['category', 'post_tag', 'product_cat'], $taxonomies->labelled());
        $this->assertSame(2, WordPress::$calls['get_object_taxonomies']);
    }

    #[Test]
    public function it_searches_the_title_the_labels_and_the_text_in_that_order(): void
    {
        WordPress::fake('get_option', ['post']);
        WordPress::fake('get_object_taxonomies', ['category', 'product_type']);
        $attributes = new DefaultSearchableAttributes(new IndexedTaxonomies(new IndexedPostTypes), new WooCommerceProductFields(static fn (): bool => false));

        $this->assertSame(['post_title', 'labels.category', 'excerpt', 'content'], $attributes->all());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_completes_a_meiliscout_document_with_facets_labels_and_text(): void
    {
        WordPress::fake('get_option', ['post']);
        WordPress::fake('get_object_taxonomies', ['category']);
        WordPress::fake('strip_shortcodes', static fn (string $markup): string => $markup);
        $hierarchy = new class implements TermHierarchy
        {
            public function ancestorsOf(int $termId, string $taxonomy): array
            {
                return [];
            }

            public function childrenOf(int $termId, string $taxonomy): array
            {
                return [];
            }
        };
        $document = new PostDocument(new TermAncestry($hierarchy), new IndexedTaxonomies(new IndexedPostTypes), new PostText, $this->shopFields());
        $term = ['term_id' => 5, 'name' => 'News', 'slug' => 'news', 'taxonomy' => 'category', 'term_taxonomy_id' => 5, 'parent' => 0];

        $completed = $document->complete(['terms' => [$term]], new WP_Post(['post_content' => '<p>Body</p>', 'post_excerpt' => 'Lead']));

        $this->assertSame(['news'], $completed['facets']['category'] ?? null);
        $this->assertSame('Lead', $completed['excerpt']);
        $this->assertSame('Body', $completed['content']);
        $this->assertArrayHasKey('labels', $completed);
        $this->assertArrayHasKey('card', $completed);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_adds_no_term_fields_to_a_document_without_a_term_list(): void
    {
        WordPress::fake('get_option', []);
        WordPress::fake('get_object_taxonomies', []);
        WordPress::fake('strip_shortcodes', static fn (string $markup): string => $markup);
        $hierarchy = new WordPressTermHierarchy;
        $document = new PostDocument(new TermAncestry($hierarchy), new IndexedTaxonomies(new IndexedPostTypes), new PostText, $this->shopFields());

        $completed = $document->complete(['terms' => 'not a list'], new WP_Post);

        $this->assertArrayNotHasKey('facets', $completed);
        $this->assertArrayNotHasKey('labels', $completed);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_reads_the_terms_around_what_another_plugin_left_in_the_list(): void
    {
        WordPress::fake('get_option', ['post']);
        WordPress::fake('get_object_taxonomies', ['category']);
        WordPress::fake('strip_shortcodes', static fn (string $markup): string => $markup);
        $hierarchy = new WordPressTermHierarchy;
        $document = new PostDocument(new TermAncestry($hierarchy), new IndexedTaxonomies(new IndexedPostTypes), new PostText, $this->shopFields());
        $term = ['term_id' => 5, 'name' => 'News', 'slug' => 'news', 'taxonomy' => 'category', 'term_taxonomy_id' => 5, 'parent' => 0];

        $completed = $document->complete(['terms' => ['junk', null, $term]], new WP_Post);

        $this->assertSame(['category' => ['news']], $completed['facets'] ?? null);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_bridges_meiliscout_hooks_to_the_module(): void
    {
        WordPress::fake('get_option', []);
        WordPress::fake('get_object_taxonomies', []);
        WordPress::fake('strip_shortcodes', static fn (string $markup): string => $markup);
        $hierarchy = new WordPressTermHierarchy;
        $postDocument = new PostDocument(new TermAncestry($hierarchy), new IndexedTaxonomies(new IndexedPostTypes), new PostText, $this->shopFields());
        $faceted = $this->indexable();
        $bridge = new MeiliScoutBridge($postDocument, $faceted);
        $plain = new PostIndexable;
        $other = $this->createStub(Indexable::class);

        $this->assertSame('words', $bridge->rankByRelevanceFirst()[0]);
        $this->assertSame('', $bridge->addModuleFields([], new WP_Post)['excerpt']);
        $this->assertSame([$faceted, $faceted], $bridge->declareFacetAttributes([$plain, $faceted]));
        $this->assertSame([$other], $bridge->declareFacetAttributes([$other]));
    }

    #[Test]
    public function it_declares_facets_sorts_and_limits_in_the_index_settings(): void
    {
        WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $key === 'meiliscout/indexed_post_types' ? ['post'] : $default);
        WordPress::fake('get_object_taxonomies', ['category']);

        $settings = $this->indexable(reachable: 500, maxFacetValues: 300)->getIndexSettings();

        $this->assertContains('facets.category', $settings['filterableAttributes']);
        $this->assertContains('parent_id', $settings['filterableAttributes']);
        $this->assertSame(500, $settings['pagination']['maxTotalHits']);
        $this->assertSame(['*' => 'count'], $settings['faceting']['sortFacetValuesBy']);
        $this->assertSame(300, $settings['faceting']['maxValuesPerFacet']);
        $this->assertSame(['title'], $settings['searchableAttributes']);
        $this->assertSame(['sku'], $settings['typoTolerance']['disableOnAttributes']);
        $this->assertContains('price', $settings['sortableAttributes']);
        $this->assertContains('card', $settings['displayedAttributes']);
        $this->assertContains('extra', $settings['displayedAttributes']);
    }

    #[Test]
    public function it_displays_every_field_when_the_project_asks_for_all_of_them(): void
    {
        WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $default);
        WordPress::fake('get_object_taxonomies', []);

        $settings = $this->indexable(displayed: ['*'])->getIndexSettings();

        $this->assertSame(['*'], $settings['displayedAttributes']);
    }

    #[Test]
    public function it_reindexes_variants_for_products_and_unknown_posts_only(): void
    {
        WordPress::fake('get_post_type', static fn (int $id): string|false => match ($id) {
            1 => 'product',
            2 => 'page',
            default => false,
        });
        $indexable = $this->indexable();

        $this->assertSame('parent_id IN [1, 3]', $indexable->dependentDocumentsFilter([1, 2, '3']));
        $this->assertNull($indexable->dependentDocumentsFilter([2]));
        $this->assertSame([], $indexable->dependentDocuments(['ID' => 'not-an-int'], null));
    }

    /**
     * @param  list<string>  $displayed
     */
    private function indexable(int $reachable = 1000, int $maxFacetValues = 1000, array $displayed = ['extra']): FacetedPostIndexable
    {
        $attributes = new readonly class($displayed) implements IndexAttributes
        {
            /** @param  list<string>  $displayed */
            public function __construct(private array $displayed) {}

            public function exactlyMatched(): array
            {
                return ['sku'];
            }

            public function filterable(): array
            {
                return ['price'];
            }

            public function sortable(): array
            {
                return ['price'];
            }

            public function displayed(): array
            {
                return $this->displayed;
            }
        };
        $searchable = new class implements SearchableAttributes
        {
            public function all(): array
            {
                return ['title'];
            }
        };

        return new FacetedPostIndexable(
            $attributes,
            $searchable,
            new IndexedTaxonomies(new IndexedPostTypes),
            new EngineLimits($reachable, $maxFacetValues),
            new VariantDocuments(new EmptyVariantFields),
        );
    }

    private function shopFields(): ShopFields
    {
        WordPress::fake('get_current_user_id', 0);
        WordPress::fake('wp_set_current_user', null);
        $cards = new class implements CardProjector
        {
            public function project(WP_Post $post): array
            {
                return ['title' => 'T'];
            }
        };

        return new ShopFields(
            $cards,
            new ProductVariants(new EmptyVariantFields, new WordPressTermHierarchy, 'medium'),
            new ProductStock,
            new ProductPopularity,
            new ProductPriceProjector,
            new ShopTaxLocation,
            new AnonymousVisitor,
        );
    }

    private function fakeAttachment(int $id): void
    {
        WordPress::fake('wp_get_attachment_image_src', ['https://example.test/a.jpg', 300, 200]);
        WordPress::fake('get_post_meta', 'A &quot;cat&quot;');
        WordPress::fake('wp_get_attachment_image_srcset', 'a-300.jpg 300w');
        WordPress::fake('wp_get_attachment_image_sizes', '(max-width: 300px) 100vw');
        unset($id);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $values, array $keys): array
    {
        return array_intersect_key($values, array_flip($keys));
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function constantProjector(array $card): CardProjector
    {
        return new readonly class($card) implements CardProjector
        {
            /** @param  array<string, mixed>  $card */
            public function __construct(private array $card) {}

            public function project(WP_Post $post): array
            {
                return $this->card;
            }
        };
    }
}
