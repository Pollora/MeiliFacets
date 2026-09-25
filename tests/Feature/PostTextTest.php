<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\PostText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Post;

/** Runs here rather than standalone: shortcodes and word counts are WordPress's. */
final class PostTextTest extends TestCase
{
    private const string BLOCKS = '<!-- wp:heading --><h2 class="wp-block-heading">Routine</h2><!-- /wp:heading -->'
        .'<!-- wp:spacer {"height":"20px"} --><div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>'
        .'<!-- /wp:spacer --><!-- wp:paragraph --><p>Laits &amp; crèmes, l&#8217;essentiel.</p><!-- /wp:paragraph -->';

    private const int WORDS = 55;

    #[Test]
    public function it_reads_the_content_alone_as_plain_text(): void
    {
        $content = new PostText()->content($this->aPost(self::BLOCKS, 'Un geste simple'));

        $this->assertSame('Routine Laits & crèmes, l’essentiel.', $content);
    }

    #[Test]
    public function it_leaves_nothing_of_the_block_markup_to_find(): void
    {
        $content = new PostText()->content($this->aPost(self::BLOCKS));

        foreach (['spacer', 'wp:', 'paragraph', 'class', 'height', '&amp;'] as $markup) {
            $this->assertStringNotContainsString($markup, $content);
        }
    }

    #[Test]
    public function it_reads_the_excerpt_alone_as_plain_text(): void
    {
        $excerpt = new PostText()->excerpt($this->aPost(self::BLOCKS, '<p>Un <strong>geste</strong> [caption]x[/caption] &amp; doux</p>'));

        $this->assertSame('Un geste & doux', $excerpt);
    }

    #[Test]
    public function it_drops_the_registered_shortcodes(): void
    {
        $content = new PostText()->content($this->aPost('Avant [caption id="1"]Légende[/caption] après'));

        $this->assertSame('Avant après', $content);
    }

    #[Test]
    public function it_summarises_with_the_excerpt_the_author_wrote(): void
    {
        $this->assertSame('Un geste simple', new PostText()->summary($this->aPost(self::BLOCKS, 'Un geste simple'), self::WORDS));
    }

    #[Test]
    public function it_summarises_with_the_opening_of_the_content_without_an_excerpt(): void
    {
        $this->assertSame('Routine Laits & crèmes, l’essentiel.', new PostText()->summary($this->aPost(self::BLOCKS), self::WORDS));
    }

    #[Test]
    public function it_bounds_the_summary_to_the_words_it_is_given(): void
    {
        $this->assertSame('Routine Laits &…', new PostText()->summary($this->aPost(self::BLOCKS), 3));
    }

    /** `wp_trim_words()` strips tags: counted after decoding, `&lt;b&gt;` would vanish. */
    #[Test]
    public function it_keeps_an_escaped_tag_as_text_in_the_summary(): void
    {
        $this->assertSame('Formule <b> douce', new PostText()->summary($this->aPost('<p>Formule &lt;b&gt; douce</p>'), self::WORDS));
    }

    #[Test]
    public function it_says_nothing_of_a_password_protected_post(): void
    {
        $protected = new WP_Post((object) ['post_content' => '<p>Réservé</p>', 'post_excerpt' => 'Secret', 'post_password' => 'x']);

        $this->assertSame('', new PostText()->content($protected));
        $this->assertSame('', new PostText()->excerpt($protected));
        $this->assertSame('', new PostText()->summary($protected, self::WORDS));
    }

    #[Test]
    public function it_has_nothing_to_say_about_an_empty_post(): void
    {
        $this->assertSame('', new PostText()->content($this->aPost('')));
        $this->assertSame('', new PostText()->summary($this->aPost('<!-- wp:spacer --><div></div><!-- /wp:spacer -->'), self::WORDS));
    }

    private function aPost(string $content, string $excerpt = ''): WP_Post
    {
        return new WP_Post((object) ['post_content' => $content, 'post_excerpt' => $excerpt, 'post_type' => 'post']);
    }
}
