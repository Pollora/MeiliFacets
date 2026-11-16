<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Indexing\PostText;
use Modules\MeiliFacets\Indexing\SummaryCardProjector;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceCardProjector;
use Tests\TestCase;
use WC_Product;
use WooCommerce;
use WP_Post;

trait SummaryCardProjectorSupport
{
    private const string EXCERPT_LENGTH_FILTER = 'excerpt_length';

    private function shopCard(): WooCommerceCardProjector
    {
        return new WooCommerceCardProjector($this->card(), $this->summaryCard());
    }

    private function summaryCard(): SummaryCardProjector
    {
        return new SummaryCardProjector($this->card(), new PostText);
    }

    private function card(): CardProjector
    {
        return new readonly class implements CardProjector
        {
            /**
             * @return array<string, mixed>
             */
            public function project(WP_Post $post): array
            {
                return ['from' => 'card'];
            }
        };
    }

    private function article(string $content): WP_Post
    {
        return new WP_Post((object) ['post_content' => $content, 'post_excerpt' => '', 'post_type' => 'post']);
    }

    private function aPublishedProduct(): WC_Product
    {
        $products = wc_get_products(['status' => 'publish', 'limit' => 1]);

        if ($products === []) {
            $this->markTestSkipped('The catalogue has no published product.');
        }

        return $products[0];
    }

    private function requireWooCommerce(): void
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The product card is WooCommerce\'s.');
        }
    }
}

uses(TestCase::class, SummaryCardProjectorSupport::class);

it('adds the summary to the card it decorates', function (): void {
    $card = $this->summaryCard()->project($this->article('<p>Un geste simple.</p>'));

    expect($card)->toBe(['from' => 'card', CardField::Summary->value => 'Un geste simple.']);
});

/** A card with nothing to say keeps the shape of one that has no image. */
it('leaves the summary out when the post says nothing', function (): void {
    expect($this->summaryCard()->project($this->article('')))->toBe(['from' => 'card']);
});

it('bounds the opening to the length the theme asks for', function (): void {
    $threeWords = static fn (): int => 3;
    add_filter(self::EXCERPT_LENGTH_FILTER, $threeWords);

    try {
        $card = $this->summaryCard()->project($this->article('<p>Un geste simple et doux.</p>'));
    } finally {
        remove_filter(self::EXCERPT_LENGTH_FILTER, $threeWords);
    }

    expect($card[CardField::Summary->value])->toBe('Un geste simple…');
});

it('gives an article the summary card once woocommerce is there', function (): void {
    $this->requireWooCommerce();

    $card = $this->shopCard()->project($this->article('<p>Un geste simple.</p>'));

    expect($card)->toBe(['from' => 'card', CardField::Summary->value => 'Un geste simple.']);
});

it('leaves the product card as it was', function (): void {
    $this->requireWooCommerce();
    $product = $this->aPublishedProduct();

    $card = $this->shopCard()->project(get_post($product->get_id()));

    expect($card)->toBe(['from' => 'card', CardField::Price->value => $product->get_price_html()]);
});
