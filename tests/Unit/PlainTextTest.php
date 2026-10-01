<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\PlainText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlainTextTest extends TestCase
{
    #[Test]
    public function it_decodes_what_wordpress_stores_encoded(): void
    {
        $this->assertSame('Crème & Soins', PlainText::from('Crème &amp; Soins'));
    }

    #[Test]
    public function it_decodes_the_numeric_entity_wptexturize_emits(): void
    {
        $this->assertSame('Soin & Douceur', PlainText::from('Soin &#038; Douceur'));
    }

    #[Test]
    public function it_leaves_plain_accented_text_untouched(): void
    {
        $this->assertSame('Crème « bio »', PlainText::from('Crème « bio »'));
    }

    #[Test]
    public function it_decodes_quotes_so_an_attribute_is_not_double_encoded(): void
    {
        $this->assertSame('5" band', PlainText::from('5&quot; band'));
        $this->assertSame("L'Occitane", PlainText::from('L&#039;Occitane'));
    }

    #[Test]
    public function it_drops_block_delimiters_and_the_attributes_they_carry(): void
    {
        $markup = '<!-- wp:spacer {"height":"20px"} --><div style="height:20px" class="wp-block-spacer"></div>'
            .'<!-- /wp:spacer --><!-- wp:paragraph --><p>Hydrate</p><!-- /wp:paragraph -->';

        $this->assertSame('Hydrate', PlainText::fromMarkup($markup));
    }

    #[Test]
    public function it_keeps_a_word_boundary_between_adjacent_elements(): void
    {
        $this->assertSame('Visage Corps', PlainText::fromMarkup('<ul><li>Visage</li><li>Corps</li></ul>'));
    }

    #[Test]
    public function it_drops_the_code_a_script_or_a_style_carries(): void
    {
        $this->assertSame('Avant Après', PlainText::fromMarkup('Avant<script>track("x")</script><style>p{}</style>Après'));
    }

    #[Test]
    public function it_decodes_entities_and_folds_every_kind_of_space_into_one(): void
    {
        $this->assertSame('Crème & soin 22 €', PlainText::fromMarkup("<p>Crème &amp;\n\t soin</p>\n<p>22&nbsp;&euro;</p>"));
    }

    #[Test]
    public function it_reads_an_escaped_tag_as_text(): void
    {
        $this->assertSame('a <b> c', PlainText::fromMarkup('<p>a &lt;b&gt; c</p>'));
    }

    /** What is stripped next must not mistake a decoded `&lt;` for a tag. */
    #[Test]
    public function it_leaves_entities_encoded_while_it_only_removes_tags(): void
    {
        $this->assertSame('a &lt;b&gt; c', trim(PlainText::withoutTags('<p>a &lt;b&gt; c</p>')));
    }
}
