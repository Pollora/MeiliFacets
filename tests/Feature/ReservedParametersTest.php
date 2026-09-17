<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Support\ReservedParameters;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;

final class ReservedParametersTest extends TestCase
{
    private const string PROBE = 'meilifacets_probe';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The names these tests expect are declared by WooCommerce.');
        }
    }

    #[Test]
    public function it_adds_the_query_vars_plugins_declare_through_the_filter(): void
    {
        $names = new ReservedParameters()->wordPress();

        $this->assertContains('min_price', $names);
        $this->assertContains('checkout-link', $names);
    }

    #[Test]
    public function it_lists_each_name_once(): void
    {
        $names = new ReservedParameters()->wordPress();

        $this->assertSame(array_values(array_unique($names)), $names);
    }

    #[Test]
    public function it_names_the_price_bounds_for_what_woocommerce_reads_even_once_they_are_query_vars(): void
    {
        $reserved = new ReservedParameters;

        $this->assertContains('min_price', $reserved->wordPress());
        $this->assertStringContainsString('$_GET', (string) $reserved->reason('min_price'));
    }

    #[Test]
    public function it_takes_the_list_of_a_parsed_request_as_wordpress_filtered_it(): void
    {
        $probe = static fn (array $names): array => [...$names, self::PROBE];
        add_filter('query_vars', $probe);

        try {
            $names = $this->whileActionCount('parse_request', 1, new ReservedParameters()->wordPress(...));
        } finally {
            remove_filter('query_vars', $probe);
        }

        $this->assertNotContains(self::PROBE, $names);
    }

    #[Test]
    public function it_reads_the_list_again_once_wordpress_has_initialised(): void
    {
        $reserved = new ReservedParameters;

        $this->assertNotContains('checkout-link', $this->whileActionCount('init', 0, $reserved->wordPress(...)));
        $this->assertContains('checkout-link', $reserved->wordPress());
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function whileActionCount(string $action, int $count, Closure $read): mixed
    {
        global $wp_actions;

        $actual = $wp_actions[$action] ?? 0;
        $wp_actions[$action] = $count;

        try {
            return $read();
        } finally {
            $wp_actions[$action] = $actual;
        }
    }
}
