<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceProductFields;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\SearchableType;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\AttributesToSearchOn;
use Modules\MeiliFacets\SiteSearch\SearchablePostTypes;
use Modules\MeiliFacets\SiteSearch\SearchableTypeFactory;
use Modules\MeiliFacets\SiteSearch\SearchTypeRefused;
use Modules\MeiliFacets\SiteSearch\UnsearchableFields;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;
use Modules\MeiliFacets\SiteSearch\WordPressSearchableTypes;
use Modules\MeiliFacets\Support\YoastSeo;
use Modules\MeiliFacets\Tests\Unit\Doubles\BuildsResolvedListings;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\DocumentTitle;
use Modules\MeiliFacets\View\YoastCanonicals;
use Modules\MeiliFacets\View\YoastPageCount;
use Modules\MeiliFacets\View\YoastPageMetadata;
use Modules\MeiliFacets\View\YoastPageWords;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_Post_Type;
use Yoast\WP\SEO\Models\Indexable;

/** Reads WordPress helpers, so each test gets a process of its own: see `Doubles\WordPress`. */
final class SiteSearchAndSeoTest extends TestCase
{
    use BuildsResolvedListings;

    protected function setUp(): void
    {
        WordPress::boot();
        WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $key === 'meiliscout/indexed_post_types' ? ['post', 'page', 'product', 'attachment'] : $default);
        WordPress::fake('get_post_type_object', static fn (string $type): WP_Post_Type|false => match ($type) {
            'post' => new WP_Post_Type('Posts', 'All posts'),
            'page' => new WP_Post_Type('Pages', 'All pages'),
            'product' => new WP_Post_Type('Products', 'All products'),
            'attachment' => new WP_Post_Type('Media', 'All media', exclude_from_search: true),
            default => false,
        });
        WordPress::fake('get_object_taxonomies', static fn (string $type): array => $type === 'post' ? ['category'] : []);
        WordPress::fake('get_post_type_archive_link', static fn (string $type): string|false => $type === 'post' ? 'https://example.test/blog/' : false);
    }

    #[Test]
    public function it_lists_the_public_indexed_post_types_that_are_not_hidden_from_search(): void
    {
        $postTypes = new SearchablePostTypes(new IndexedPostTypes);

        $this->assertSame(['post', 'page', 'product'], $postTypes->all());
        $this->assertTrue($postTypes->contains('page'));
        $this->assertFalse($postTypes->contains('attachment'));
    }

    #[Test]
    public function it_derives_a_searchable_type_from_what_wordpress_registered(): void
    {
        $type = $this->factory()->forPostType('post');

        $this->assertSame('Posts', $type->heading);
        $this->assertSame('All posts', $type->seeAllLabel);
        $this->assertSame('https://example.test/blog/', $type->archive);
        $this->assertSame(['post_title', 'labels.category', 'excerpt'], $type->searchOn);
        $this->assertSame(SearchableTypeFactory::CARD, $type->card);
        $this->assertSame(['post_type = "post"', 'post_status = "publish"'], $type->baseFilter);
        $this->assertNull($this->factory()->forPostType('page')->archive);
    }

    #[Test]
    public function it_refuses_a_post_type_wordpress_does_not_know(): void
    {
        $this->expectException(SearchTypeRefused::class);

        $this->factory()->forPostType('ghost');
    }

    #[Test]
    public function it_derives_every_searchable_type_but_products_and_remembers_them(): void
    {
        $types = new WordPressSearchableTypes(new SearchablePostTypes(new IndexedPostTypes), $this->factory());

        $this->assertSame(['post', 'page'], array_keys($types->all()));
        $this->assertSame($types->all(), $types->all());
        $this->assertSame(2, WordPress::$calls['get_post_type_archive_link']);
    }

    #[Test]
    public function it_adds_the_shop_products_first_only_while_the_plugin_is_active_and_indexed(): void
    {
        $postTypes = new SearchablePostTypes(new IndexedPostTypes);
        $wordPress = new WordPressSearchableTypes($postTypes, $this->factory());
        $fields = new WooCommerceProductFields(static fn (): bool => true);

        $active = new WooCommerceSearchableTypes($wordPress, $postTypes, $this->factory(), $fields, static fn (): bool => true);
        $inactive = new WooCommerceSearchableTypes($wordPress, $postTypes, $this->factory(), $fields, static fn (): bool => false);

        $this->assertSame(['product', 'post', 'page'], array_keys($active->all()));
        $this->assertSame($active->all()['product'], $active->all()['product']);
        $this->assertSame(['post', 'page'], array_keys($inactive->all()));
    }

    #[Test]
    public function it_accepts_the_declared_types_the_index_holds_and_refuses_fields_it_does_not_search(): void
    {
        $declared = new class implements SearchableTypes
        {
            public function all(): array
            {
                return [
                    'post' => new SearchableType('post', 'Posts', 'All', [], ['post_title'], 'card', null),
                    'recipe' => new SearchableType('recipe', 'Recipes', 'All', [], ['post_title'], 'card', null),
                ];
            }
        };

        $accepted = new AcceptedSearchTypes($declared, new IndexedPostTypes, $this->searchOn())->all();

        $this->assertSame(['post'], array_keys($accepted));
    }

    #[Test]
    public function it_names_the_fields_a_declared_type_searches_without_the_index_searching_them(): void
    {
        $declared = new class implements SearchableTypes
        {
            public function all(): array
            {
                return ['post' => new SearchableType('post', 'Posts', 'All', [], ['post_title', 'secret'], 'card', null)];
            }
        };

        try {
            new AcceptedSearchTypes($declared, new IndexedPostTypes, $this->searchOn())->all();
            $this->fail('A field the index does not search was accepted.');
        } catch (UnsearchableFields $failure) {
            $this->assertStringContainsString('"post" is searched on fields the index does not search: secret', $failure->getMessage());
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_says_whether_yoast_is_active_by_the_function_it_defines(): void
    {
        $this->assertFalse(YoastSeo::isActive());

        WordPress::bootYoast();

        $this->assertTrue(YoastSeo::isActive());
    }

    #[Test]
    public function it_words_the_page_the_way_yoast_does_and_names_it_after_the_first_page_only(): void
    {
        $this->fakeYoast();
        $words = new YoastPageWords;

        $this->assertSame('- Page 3 of 9', $words->of(3, 9));
        $this->assertSame('- Page :page of :pages', $words->withPlaceholders());
        $this->assertTrue($words->names(2, 5));
        $this->assertFalse($words->names(1, 5));
        $this->assertFalse($words->names(2, 1));
    }

    #[Test]
    public function it_opens_the_page_words_with_the_title_separator_yoast_is_set_to(): void
    {
        $this->fakeYoast(separator: '|');

        $this->assertSame('| Page :page of :pages', new YoastPageWords()->withPlaceholders());
    }

    #[Test]
    public function it_hands_yoast_the_real_page_count_of_the_engine(): void
    {
        $this->fakeYoast();
        $page = new ListingPage;
        $page->markAsCurrent($this->resolved(
            new FakeListing(perPage: 10),
            new ListingState(page: 2),
            new FakeSearchEngine(['results' => ['totalHits' => 45, 'hits' => []]]),
        ));
        $count = new YoastPageCount($page, new YoastPageWords);

        $this->assertSame(
            ['%%page%%' => '- Page 2 of 5', '%%pagetotal%%' => '5', 'other' => 'x'],
            $count->countTheEnginePages(['%%page%%' => 'stale', '%%pagetotal%%' => '0', 'other' => 'x'])
        );
        $this->assertSame(['%%pagetotal%%' => '5'], $count->countTheEnginePages(['%%pagetotal%%' => '0']));
    }

    #[Test]
    public function it_leaves_yoasts_replacements_alone_without_a_listing_or_a_page_variable(): void
    {
        $this->fakeYoast();
        $replacements = ['%%page%%' => 'x'];

        $this->assertSame($replacements, new YoastPageCount(new ListingPage, new YoastPageWords)->countTheEnginePages($replacements));

        $page = new ListingPage;
        $page->markAsCurrent($this->resolved(new FakeListing, engine: new FakeSearchEngine(['results' => ['totalHits' => 45]])));
        $this->assertSame(['other' => 'y'], new YoastPageCount($page, new YoastPageWords)->countTheEnginePages(['other' => 'y']));
    }

    #[Test]
    public function it_leaves_the_page_variable_empty_on_the_first_page(): void
    {
        $this->fakeYoast();
        $page = new ListingPage;
        $page->markAsCurrent($this->resolved(new FakeListing(perPage: 10), engine: new FakeSearchEngine(['results' => ['totalHits' => 45]])));

        $this->assertSame(['%%page%%' => '', '%%pagetotal%%' => '5'], new YoastPageCount($page, new YoastPageWords)->countTheEnginePages(['%%page%%' => 'x', '%%pagetotal%%' => 'y']));
    }

    #[Test]
    public function it_asks_yoast_for_the_title_of_the_first_page_and_of_a_later_one(): void
    {
        $this->fakeYoast();
        $filter = null;
        WordPress::fake('add_filter', static function (string $hook, \Closure $callback) use (&$filter): void {
            $filter = $callback;
        });
        WordPress::fake('wp_get_document_title', static function () use (&$filter): string {
            $replacements = $filter(['%%sitename%%' => 'Store']);

            return implode('/', [$replacements['%%page%%'], $replacements['%%pagenumber%%'], $replacements['%%pagetotal%%']]);
        });
        $metadata = new YoastPageMetadata(new DocumentTitle, new YoastPageWords, new YoastCanonicals);
        $this->fakeCanonical(canonical: '');

        $this->assertSame(
            ['first' => '/:page/:pages', 'paged' => '- Page :page of :pages/:page/:pages'],
            $metadata->titles()
        );
        $this->assertNull($metadata->canonicals());
    }

    #[Test]
    public function it_prints_a_canonical_that_is_the_same_on_every_page_when_it_is_custom_or_singular(): void
    {
        $this->fakeCanonical(canonical: 'https://example.test/custom/', custom: 'https://example.test/custom/');
        $this->assertSame(['first' => 'https://example.test/custom/', 'paged' => 'https://example.test/custom/'], new YoastCanonicals()->byPage());

        $this->fakeCanonical(canonical: 'https://example.test/about/', custom: '');
        WordPress::fake('is_singular', true);
        $this->assertSame(['first' => 'https://example.test/about/', 'paged' => 'https://example.test/about/'], new YoastCanonicals()->byPage());
    }

    #[Test]
    public function it_writes_the_canonical_of_each_page_of_an_archive_from_its_permalink(): void
    {
        $this->fakeCanonical(canonical: 'https://example.test/shop/page/3/', permalink: 'https://example.test/shop/', page: 3);

        $this->assertSame(
            ['first' => 'https://example.test/shop/', 'paged' => 'https://example.test/shop/page/:page/'],
            new YoastCanonicals()->byPage()
        );
    }

    #[Test]
    public function it_reads_the_permalink_as_the_canonical_of_the_first_page(): void
    {
        $this->fakeCanonical(canonical: 'https://example.test/shop/', permalink: 'https://example.test/shop/', page: 1);

        $this->assertSame('https://example.test/shop/', new YoastCanonicals()->byPage()['first'] ?? null);
    }

    #[Test]
    public function it_prints_no_canonical_when_a_filter_wrote_one_the_model_cannot_tell(): void
    {
        $this->fakeCanonical(canonical: 'https://elsewhere.test/', permalink: 'https://example.test/shop/', page: 1);

        $this->assertNull(new YoastCanonicals()->byPage());
    }

    private function factory(): SearchableTypeFactory
    {
        return new SearchableTypeFactory($this->searchOn());
    }

    private function searchOn(): AttributesToSearchOn
    {
        return new AttributesToSearchOn(new class implements SearchableAttributes
        {
            public function all(): array
            {
                return ['post_title', 'labels.category', 'excerpt', 'content'];
            }
        });
    }

    private function fakeYoast(string $separator = '-'): void
    {
        WordPress::bootYoast();
        WordPress::fake('YoastSEO', (object) ['helpers' => (object) ['options' => new readonly class($separator)
        {
            public function __construct(private string $separator) {}

            public function get_title_separator(): string
            {
                return $this->separator;
            }
        }]]);
    }

    private function fakeCanonical(string $canonical, string $custom = '', string $permalink = '', int $page = 1): void
    {
        WordPress::bootYoast();
        $indexable = new Indexable;
        $indexable->canonical = $custom;
        $pagination = new readonly class($page)
        {
            public function __construct(private int $page) {}

            public function get_current_archive_page_number(): int
            {
                return $this->page;
            }

            public function get_paginated_url(string $url, int $page): string
            {
                return rtrim($url, '/')."/page/{$page}/";
            }
        };
        WordPress::fake('YoastSEO', (object) [
            'meta' => new readonly class($canonical, $permalink, $indexable)
            {
                public function __construct(private string $canonical, private string $permalink, private Indexable $indexable) {}

                public function for_current_page(): \stdClass
                {
                    return (object) ['canonical' => $this->canonical, 'permalink' => $this->permalink, 'indexable' => $this->indexable];
                }
            },
            'helpers' => (object) ['pagination' => $pagination, 'options' => new class
            {
                public function get_title_separator(): string
                {
                    return '-';
                }
            }],
        ]);
    }
}
