<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\ReservedParameters;
use Modules\MeiliFacets\Support\UrlParameters;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReservedParametersTest extends TestCase
{
    #[Test]
    public function it_refuses_a_name_a_plugin_reads_from_the_query_string(): void
    {
        $reserved = new ReservedParameters;

        foreach (['min_price', 'max_price', 'rating_filter', 'orderby'] as $parameter) {
            $this->assertNotNull($reserved->reason($parameter), $parameter.' is taken and went unnoticed.');
        }
    }

    #[Test]
    public function it_refuses_every_name_under_woocommerce_filter_prefix(): void
    {
        $this->assertNotNull(new ReservedParameters()->reason('filter_contenance'));
    }

    #[Test]
    public function it_leaves_a_name_nothing_claims_alone(): void
    {
        $this->assertNull(new ReservedParameters()->reason('price_min'));
    }

    #[Test]
    public function it_accepts_the_price_bounds_while_they_keep_their_default_names(): void
    {
        $accepted = new ReservedParameters()->acceptedFor(new UrlParameters([]));

        $this->assertSame(['min_price', 'max_price'], $accepted);
    }

    #[Test]
    public function it_stops_accepting_a_bound_the_project_renamed(): void
    {
        $accepted = new ReservedParameters()->acceptedFor(new UrlParameters([], ['min_price' => 'prix_min']));

        $this->assertSame(['max_price'], $accepted);
    }

    #[Test]
    public function it_names_every_offender_it_was_handed(): void
    {
        $conflicts = new ReservedParameters()->conflicts(['categorie', 'min_price', 'utm_source']);

        $this->assertSame(['min_price', 'utm_source'], array_keys($conflicts));
    }
}
