<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Http\RobotsPolicy;
use Modules\MeiliFacets\Support\UrlParameters;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RobotsPolicyTest extends TestCase
{
    private RobotsPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new RobotsPolicy(new UrlParameters(['product_brand' => 'marque']));
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

    /**
     * A paginated page has to stay indexable: the products it holds have no
     * other internal link.
     */
    #[Test]
    public function it_leaves_a_page_indexable(): void
    {
        $this->assertFalse($this->policy->appliesTo(['pg' => '2']));
    }

    /**
     * The reserved names are global, so a campaign link carrying `?q=` must not
     * take the home page out of the index.
     */
    #[Test]
    public function it_leaves_a_sort_or_a_search_indexable(): void
    {
        $this->assertFalse($this->policy->appliesTo(['sort' => 'price_asc']));
        $this->assertFalse($this->policy->appliesTo(['q' => 'bonjour']));
    }

    #[Test]
    public function it_ignores_a_parameter_that_belongs_to_nothing(): void
    {
        $this->assertFalse($this->policy->appliesTo(['utm_source' => 'newsletter']));
    }

    #[Test]
    public function it_ignores_a_facet_left_empty(): void
    {
        $this->assertFalse($this->policy->appliesTo(['marque' => '']));
        $this->assertFalse($this->policy->appliesTo(['marque' => '   ']));
        $this->assertFalse($this->policy->appliesTo(['marque' => ['acme']]));
    }
}
