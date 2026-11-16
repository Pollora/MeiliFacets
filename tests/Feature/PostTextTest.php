<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Indexing\PostText;
use Tests\TestCase;
use WP_Post;

trait PostTextSupport
{
    private const string BLOCKS = '<!-- wp:heading --><h2 class="wp-block-heading">Routine</h2><!-- /wp:heading -->'
        .'<!-- wp:spacer {"height":"20px"} --><div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>'
        .'<!-- /wp:spacer --><!-- wp:paragraph --><p>Soins &amp; crèmes, l&#8217;indispensable.</p><!-- /wp:paragraph -->';

    private const int WORDS = 55;

    private function aPost(string $content, string $excerpt = ''): WP_Post
    {
        return new WP_Post((object) ['post_content' => $content, 'post_excerpt' => $excerpt, 'post_type' => 'post']);
    }
}

/** Runs here rather than standalone: shortcodes and word counts are WordPress's. */
uses(TestCase::class, PostTextSupport::class);

it('reads the content alone as plain text', function (): void {
    $content = new PostText()->content($this->aPost(self::BLOCKS, 'Un geste simple'));

    expect($content)->toBe('Routine Soins & crèmes, l’indispensable.');
});

it('leaves nothing of the block markup to find', function (): void {
    $content = new PostText()->content($this->aPost(self::BLOCKS));

    foreach (['spacer', 'wp:', 'paragraph', 'class', 'height', '&amp;'] as $markup) {
        $this->assertStringNotContainsString($markup, $content);
    }
});

it('reads the excerpt alone as plain text', function (): void {
    $excerpt = new PostText()->excerpt($this->aPost(self::BLOCKS, '<p>Un <strong>geste</strong> [caption]x[/caption] &amp; doux</p>'));

    expect($excerpt)->toBe('Un geste & doux');
});

it('drops the registered shortcodes', function (): void {
    $content = new PostText()->content($this->aPost('Avant [caption id="1"]Légende[/caption] après'));

    expect($content)->toBe('Avant après');
});

it('summarises with the excerpt the author wrote', function (): void {
    expect(new PostText()->summary($this->aPost(self::BLOCKS, 'Un geste simple'), self::WORDS))->toBe('Un geste simple');
});

it('summarises with the opening of the content without an excerpt', function (): void {
    expect(new PostText()->summary($this->aPost(self::BLOCKS), self::WORDS))->toBe('Routine Soins & crèmes, l’indispensable.');
});

/** WordPress trims only the excerpt it generates: the one an author wrote is shown whole. */
it('keeps the excerpt the author wrote whole', function (): void {
    $summary = new PostText()->summary($this->aPost(self::BLOCKS, 'Un geste simple, doux et quotidien'), 3);

    expect($summary)->toBe('Un geste simple, doux et quotidien');
});

it('bounds the opening of the content to the words it is given', function (): void {
    expect(new PostText()->summary($this->aPost(self::BLOCKS), 3))->toBe('Routine Soins &…');
});

/** `wp_trim_words()` strips tags: counted after decoding, `&lt;b&gt;` would vanish. */
it('keeps an escaped tag as text in the summary', function (): void {
    expect(new PostText()->summary($this->aPost('<p>Formule &lt;b&gt; douce</p>'), self::WORDS))->toBe('Formule <b> douce');
});

it('says nothing of a password protected post', function (): void {
    $protected = new WP_Post((object) ['post_content' => '<p>Réservé</p>', 'post_excerpt' => 'Secret', 'post_password' => 'x']);

    expect(new PostText()->content($protected))->toBe('');
    expect(new PostText()->excerpt($protected))->toBe('');
    expect(new PostText()->summary($protected, self::WORDS))->toBe('');
});

it('has nothing to say about an empty post', function (): void {
    expect(new PostText()->content($this->aPost('')))->toBe('');
    expect(new PostText()->summary($this->aPost('<!-- wp:spacer --><div></div><!-- /wp:spacer -->'), self::WORDS))->toBe('');
});
