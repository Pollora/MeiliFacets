<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Indexing\PostText;
use Modules\MeiliFacets\Indexing\SummaryCardProjector;
use Modules\MeiliFacets\Indexing\WooCommerceCardProjector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Product;
use WooCommerce;
use WP_Post;

final class SummaryCardProjectorTest extends TestCase
{
    private const string EXCERPT_LENGTH_FILTER = 'excerpt_length';

    #[Test]
    public function it_adds_the_summary_to_the_card_it_decorates(): void
    {
        $card = $this->summaryCard()->project($this->article('<p>Un geste simple.</p>'));

        $this->assertSame(['from' => 'card', CardField::Summary->value => 'Un geste simple.'], $card);
    }

    /** A card with nothing to say keeps the shape of one that has no image. */
    #[Test]
    public function it_leaves_the_summary_out_when_the_post_says_nothing(): void
    {
        $this->assertSame(['from' => 'card'], $this->summaryCard()->project($this->article('')));
    }

    #[Test]
    public function it_bounds_the_opening_to_the_length_the_theme_asks_for(): void
    {
        $threeWords = static fn (): int => 3;
        add_filter(self::EXCERPT_LENGTH_FILTER, $threeWords);

        try {
            $card = $this->summaryCard()->project($this->article('<p>Un geste simple et doux.</p>'));
        } finally {
            remove_filter(self::EXCERPT_LENGTH_FILTER, $threeWords);
        }

        $this->assertSame('Un geste simple…', $card[CardField::Summary->value]);
    }

    #[Test]
    public function it_gives_an_article_the_summary_card_once_woocommerce_is_there(): void
    {
        $this->requireWooCommerce();

        $card = $this->shopCard()->project($this->article('<p>Un geste simple.</p>'));

        $this->assertSame(['from' => 'card', CardField::Summary->value => 'Un geste simple.'], $card);
    }

    #[Test]
    public function it_leaves_the_product_card_as_it_was(): void
    {
        $this->requireWooCommerce();
        $product = $this->aPublishedProduct();

        $card = $this->shopCard()->project(get_post($product->get_id()));

        $this->assertSame(['from' => 'card', CardField::Price->value => $product->get_price_html()], $card);
    }

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
