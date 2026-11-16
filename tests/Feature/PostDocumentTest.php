<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Indexing\PostDocument;
use Modules\MeiliFacets\Indexing\WooCommerce\ShopFields;
use Modules\MeiliFacets\Tests\Feature\Concerns\PinsIndexedPostTypes;
use Tests\TestCase;
use WP_Post;

trait PostDocumentSupport
{
    private const array CARD = ['title' => 'Routine'];

    private const string ATTRIBUTE = 'pa_test_volume';

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
        $shopFields = $this->app->make(ShopFields::class, ['cards' => $this->card($card)]);

        return $this->app->make(PostDocument::class, ['shopFields' => $shopFields]);
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

/** The fields the module adds to a MeiliScout document, as the single hook writes them. */
uses(TestCase::class, PinsIndexedPostTypes::class, PostDocumentSupport::class);

beforeEach(function (): void {
    $this->pinIndexedPostTypes();
    register_taxonomy(self::ATTRIBUTE, 'post', ['public' => false]);
});

afterEach(function (): void {
    unregister_taxonomy(self::ATTRIBUTE);
    $this->unpinIndexedPostTypes();
});

it('files term names under their taxonomy', function (): void {
    $labels = $this->completed([
        $this->term(1, 'Visage', 'category'),
        $this->term(2, 'Laits &amp; crèmes', 'category'),
    ])[DocumentField::Labels->value];

    expect($labels)->toBe(['category' => ['Visage', 'Laits & crèmes']]);
});

/** A WooCommerce attribute without archives is not viewable, yet its values are what a visitor types. */
it('labels a taxonomy without archives', function (): void {
    $labels = $this->completed([$this->term(1, '100ml', self::ATTRIBUTE)])[DocumentField::Labels->value];

    expect($labels)->toBe([self::ATTRIBUTE => ['100ml']]);
});

/** `exclude-from-search` is a term of `product_visibility`: labelled, it would be found by its own name. */
it('labels no term of a technical taxonomy yet filters on it', function (): void {
    $document = $this->completed([$this->term(1, 'exclude-from-search', 'product_visibility')]);

    expect($document[DocumentField::Labels->value])->toBe([]);
    expect($document[DocumentField::Facets->value])->toBe(['product_visibility' => ['exclude-from-search']]);
});

it('facets and labels nothing on a document without terms', function (): void {
    $document = $this->postDocument()->complete(['ID' => 1], $this->article());

    expect($document[DocumentField::Facets->value])->toBe([]);
    expect($document[DocumentField::Labels->value])->toBe([]);
});

/** Each field used to come from its own filter: an unreadable `terms` never stopped the others. */
it('still projects the rest when the terms are unreadable', function (): void {
    $document = $this->postDocument()->complete([DocumentField::Terms->value => 'broken'], $this->article());

    $this->assertArrayNotHasKey(DocumentField::Facets->value, $document);
    $this->assertArrayNotHasKey(DocumentField::Labels->value, $document);
    expect($document[DocumentField::Excerpt->value])->toBe('Doux & frais');
    expect($document[DocumentField::Content->value])->toBe('Routine');
    expect($document[DocumentField::Card->value])->toBe(self::CARD);
});

it('writes the excerpt and the content as plain text', function (): void {
    $document = $this->completed([]);

    expect($document[DocumentField::Excerpt->value])->toBe('Doux & frais');
    expect($document[DocumentField::Content->value])->toBe('Routine');
});

it('leaves a card without a price off the price field', function (): void {
    $this->assertArrayNotHasKey(DocumentField::Price->value, $this->completed([]));
});

it('drops the variants and the stock flag a projector writes on a post', function (): void {
    $card = ['title' => 'Routine', CardField::Variants->value => [['price' => 26]], CardField::OutOfStock->value => true];

    $document = $this->postDocument($card)->complete([], $this->article());

    expect($document[DocumentField::Card->value])->toBe(['title' => 'Routine']);
});

/** A frozen snapshot: every field, every value and the key order, on a post that lives only in memory. */
it('writes this exact document', function (): void {
    $document = $this->postDocument()->complete(
        ['ID' => 7, DocumentField::Terms->value => [$this->term(1, 'Soins &amp; rituels', 'category')]],
        $this->article()
    );

    expect($document)->toBe([
        'ID' => 7,
        'terms' => [['term_id' => 1, 'name' => 'Soins &amp; rituels', 'slug' => 'soins-rituels', 'taxonomy' => 'category', 'parent' => 0]],
        'facets' => ['category' => ['soins-rituels']],
        'labels' => ['category' => ['Soins & rituels']],
        'excerpt' => 'Doux & frais',
        'content' => 'Routine',
        'card' => ['title' => 'Routine'],
    ]);
});
