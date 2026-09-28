<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use LogicException;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SearchRegistryTest extends TestCase
{
    #[Test]
    public function it_keeps_roots_of_different_names_apart(): void
    {
        $registry = new SearchRegistry;
        $header = $this->root('header');
        $footer = $this->root('footer');
        $registry->open($header);
        $registry->open($footer);

        $this->assertSame([$header, $footer], [$registry->named('header'), $registry->named('footer')]);
    }

    #[Test]
    public function it_hands_back_the_only_root_rendered(): void
    {
        $registry = new SearchRegistry;
        $registry->open($this->root('header'));

        $this->assertSame('header', $registry->sole()->name);
    }

    #[Test]
    public function it_refuses_a_second_root_under_the_same_name(): void
    {
        $registry = new SearchRegistry;
        $registry->open($this->root('search'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A search named "search" is already rendered');

        $registry->open($this->root('search'));
    }

    #[Test]
    public function it_names_the_candidates_when_a_brick_names_no_root(): void
    {
        $registry = new SearchRegistry;
        $registry->open($this->root('header'));
        $registry->open($this->root('footer'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Name the search: 2 are declared (header, footer)');

        $registry->sole();
    }

    #[Test]
    public function it_names_the_roots_it_knows_when_asked_for_another(): void
    {
        $registry = new SearchRegistry;
        $registry->open($this->root('header'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No search named "footer". Declared: header.');

        $registry->named('footer');
    }

    private function root(string $name): SearchRoot
    {
        return new SearchRoot($name, new SearchSettings, []);
    }
}
