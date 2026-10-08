<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use LogicException;
use Modules\MeiliFacets\SiteSearch\SearchableType;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\SiteSearch\SearchTypeRefused;
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
        $registry->add($header);
        $registry->add($footer);

        $this->assertSame([$header, $footer], [$registry->named('header'), $registry->named('footer')]);
    }

    #[Test]
    public function it_hands_back_the_only_root_rendered(): void
    {
        $registry = new SearchRegistry;
        $registry->add($this->root('header'));

        $this->assertSame('header', $registry->onlyOne()->name);
    }

    #[Test]
    public function it_refuses_a_second_root_under_the_same_name(): void
    {
        $registry = new SearchRegistry;
        $registry->add($this->root('search'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A search named "search" is already rendered');

        $registry->add($this->root('search'));
    }

    #[Test]
    public function it_names_the_candidates_when_a_brick_names_no_root(): void
    {
        $registry = new SearchRegistry;
        $registry->add($this->root('header'));
        $registry->add($this->root('footer'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Name the search: 2 are declared (header, footer)');

        $registry->onlyOne();
    }

    #[Test]
    public function it_names_the_roots_it_knows_when_asked_for_another(): void
    {
        $registry = new SearchRegistry;
        $registry->add($this->root('header'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No search named "footer". Declared: header.');

        $registry->named('footer');
    }

    #[Test]
    public function it_refuses_a_second_section_of_one_type_in_one_root(): void
    {
        $registry = new SearchRegistry;
        $header = $this->root('header');
        $registry->placeSection($header, $this->type('post'));
        $registry->placeSection($header, $this->type('product'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The search "header" already holds a section for "post": place one section per type.');

        $registry->placeSection($header, $this->type('post'));
    }

    #[Test]
    public function it_lets_each_root_hold_its_own_section_of_a_type(): void
    {
        $registry = new SearchRegistry;
        $registry->placeSection($this->root('header'), $this->type('post'));
        $registry->placeSection($this->root('footer'), $this->type('post'));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_hands_a_section_the_type_its_root_accepts(): void
    {
        $post = $this->type('post');

        $this->assertSame($post, new SearchRoot('header', new SearchSettings, ['post' => $post])->type('post'));
    }

    #[Test]
    public function it_refuses_a_section_a_type_its_root_does_not_accept_naming_those_it_does(): void
    {
        $root = new SearchRoot('header', new SearchSettings, ['product' => $this->type('product'), 'post' => $this->type('post')]);

        $this->expectException(SearchTypeRefused::class);
        $this->expectExceptionMessage('Post type "page" is not searchable: declare it in SearchableTypes and index it in MeiliScout. Searchable here: product, post.');

        $root->type('page');
    }

    private function root(string $name): SearchRoot
    {
        return new SearchRoot($name, new SearchSettings, []);
    }

    private function type(string $postType): SearchableType
    {
        return new SearchableType($postType, ucfirst($postType), 'All', [], ['post_title'], 'card', null);
    }
}
