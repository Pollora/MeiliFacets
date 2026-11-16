<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\PlainText;

it('decodes what wordpress stores encoded', function (): void {
    expect(PlainText::from('Crème &amp; Soins'))->toBe('Crème & Soins');
});

it('decodes the numeric entity wptexturize emits', function (): void {
    expect(PlainText::from('Soin &#038; Douceur'))->toBe('Soin & Douceur');
});

it('leaves plain accented text untouched', function (): void {
    expect(PlainText::from('Crème « bio »'))->toBe('Crème « bio »');
});

it('decodes quotes so an attribute is not double encoded', function (): void {
    expect(PlainText::from('5&quot; band'))->toBe('5" band');
    expect(PlainText::from('L&#039;Occitane'))->toBe("L'Occitane");
});

it('drops block delimiters and the attributes they carry', function (): void {
    $markup = '<!-- wp:spacer {"height":"20px"} --><div style="height:20px" class="wp-block-spacer"></div>'
        .'<!-- /wp:spacer --><!-- wp:paragraph --><p>Hydrate</p><!-- /wp:paragraph -->';

    expect(PlainText::fromMarkup($markup))->toBe('Hydrate');
});

it('keeps a word boundary between adjacent elements', function (): void {
    expect(PlainText::fromMarkup('<ul><li>Visage</li><li>Corps</li></ul>'))->toBe('Visage Corps');
});

it('drops the code a script or a style carries', function (): void {
    expect(PlainText::fromMarkup('Avant<script>track("x")</script><style>p{}</style>Après'))->toBe('Avant Après');
});

it('decodes entities and folds every kind of space into one', function (): void {
    expect(PlainText::fromMarkup("<p>Crème &amp;\n\t soin</p>\n<p>22&nbsp;&euro;</p>"))->toBe('Crème & soin 22 €');
});

it('reads an escaped tag as text', function (): void {
    expect(PlainText::fromMarkup('<p>a &lt;b&gt; c</p>'))->toBe('a <b> c');
});

// What is stripped next must not mistake a decoded `&lt;` for a tag.
it('leaves entities encoded while it only removes tags', function (): void {
    expect(trim(PlainText::withoutTags('<p>a &lt;b&gt; c</p>')))->toBe('a &lt;b&gt; c');
});
