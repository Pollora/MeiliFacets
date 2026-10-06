<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Http\IndexingPolicy;
use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Support\UrlParameters;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndexingPolicyTest extends TestCase
{
    private IndexingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new IndexingPolicy(new UrlParameters(['product_brand' => 'marque']), new ListingPage);
    }

    #[Test]
    public function it_leaves_a_bare_path_indexable(): void
    {
        $this->assertFalse($this->policy->appliesTo([]));
    }

    #[Test]
    public function it_covers_a_mapped_facet(): void
    {
        $this->assertTrue($this->policy->appliesTo(['marque' => 'acme']));
    }

    #[Test]
    public function it_covers_a_facet_left_unmapped(): void
    {
        $this->assertTrue($this->policy->appliesTo(['f_product_cat' => 'coats']));
    }

    /** Nothing links to page 2 since the controls became buttons: it is a duplicate with no reader. */
    #[Test]
    public function it_covers_a_page_number(): void
    {
        $this->assertTrue($this->policy->appliesTo(['pg' => '2']));
    }

    /** The same products in another order, and a search result page. */
    #[Test]
    public function it_covers_a_sort_and_a_search(): void
    {
        $this->assertTrue($this->policy->appliesTo(['sort' => 'price_asc']));
        $this->assertTrue($this->policy->appliesTo(['q' => 'bonjour']));
    }

    /** A project may rename the reserved parameters; the policy follows the mapping, not the default. */
    #[Test]
    public function it_follows_a_renamed_reserved_parameter(): void
    {
        $policy = new IndexingPolicy(new UrlParameters([], ['q' => 'recherche']), new ListingPage);

        $this->assertTrue($policy->appliesTo(['recherche' => 'bonjour']));
        $this->assertFalse($policy->appliesTo(['q' => 'bonjour']));
    }

    #[Test]
    public function it_ignores_a_parameter_that_belongs_to_nothing(): void
    {
        $this->assertFalse($this->policy->appliesTo(['utm_source' => 'newsletter']));
    }

    #[Test]
    public function it_points_a_filtered_view_to_its_bare_path(): void
    {
        $canonical = $this->policy->canonicalFor('https://shop.test/boutique', ['marque' => 'acme', 'sort' => 'newest', 'q' => 'ge']);

        $this->assertSame('https://shop.test/boutique', $canonical);
    }

    /** A page lists other products than the first: it stays its own canonical, without the filters. */
    #[Test]
    public function it_keeps_the_page_number_in_the_canonical(): void
    {
        $canonical = $this->policy->canonicalFor('https://shop.test/boutique', ['marque' => 'acme', 'pg' => '3']);

        $this->assertSame('https://shop.test/boutique?pg=3', $canonical);
    }

    #[Test]
    public function it_leaves_the_first_page_out_of_the_canonical(): void
    {
        $this->assertSame('https://shop.test/boutique', $this->policy->canonicalFor('https://shop.test/boutique', ['pg' => '1']));
    }

    #[Test]
    public function it_leaves_an_unreadable_page_out_of_the_canonical(): void
    {
        $this->assertSame('https://shop.test/boutique', $this->policy->canonicalFor('https://shop.test/boutique', ['pg' => '2"><script>']));
        $this->assertSame('https://shop.test/boutique', $this->policy->canonicalFor('https://shop.test/boutique', ['pg' => ['2']]));
    }

    #[Test]
    public function it_follows_a_renamed_page_parameter_in_the_canonical(): void
    {
        $policy = new IndexingPolicy(new UrlParameters([], ['pg' => 'page_produits']), new ListingPage);

        $this->assertSame('https://shop.test/boutique?page_produits=2', $policy->canonicalFor('https://shop.test/boutique', ['page_produits' => '2']));
    }

    #[Test]
    public function it_appends_the_page_number_to_a_canonical_that_already_has_a_query(): void
    {
        $canonical = $this->policy->canonicalFor('https://shop.test/?post_type=product', ['pg' => '2']);

        $this->assertSame('https://shop.test/?post_type=product&pg=2', $canonical);
    }

    #[Test]
    public function it_leaves_an_absent_canonical_absent(): void
    {
        $this->assertSame('', $this->policy->canonicalFor('', ['pg' => '2']));
    }

    #[Test]
    public function it_ignores_a_facet_left_empty(): void
    {
        $this->assertFalse($this->policy->appliesTo(['marque' => '']));
        $this->assertFalse($this->policy->appliesTo(['marque' => '   ']));
        $this->assertFalse($this->policy->appliesTo(['marque' => ['acme']]));
    }
}
