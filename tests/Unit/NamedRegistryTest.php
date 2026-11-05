<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\NameRefused;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class NamedRegistryTest extends TestCase
{
    #[Test]
    public function it_hands_back_the_entry_under_its_name(): void
    {
        $shoes = new stdClass;
        $registry = new FakeRegistry(['hats' => new stdClass, 'shoes' => $shoes]);

        $this->assertSame($shoes, $registry->named('shoes'));
    }

    #[Test]
    public function it_names_the_declared_entries_when_asked_for_an_unknown_one(): void
    {
        $registry = new FakeRegistry(['hats' => new stdClass, 'shoes' => new stdClass]);

        $this->expectException(NameRefused::class);
        $this->expectExceptionMessage('No shelf named "coats". Declared: hats, shoes.');

        $registry->named('coats');
    }

    #[Test]
    public function it_says_none_is_declared_when_asked_for_a_name_in_an_empty_registry(): void
    {
        $this->expectException(NameRefused::class);
        $this->expectExceptionMessage('No shelf named "hats". Declared: none.');

        new FakeRegistry()->named('hats');
    }

    #[Test]
    public function it_does_not_match_a_name_written_in_another_case(): void
    {
        $this->expectException(NameRefused::class);

        new FakeRegistry(['hats' => new stdClass])->named('Hats');
    }

    #[Test]
    public function it_hands_back_the_only_entry_without_a_name(): void
    {
        $hats = new stdClass;

        $this->assertSame($hats, new FakeRegistry(['hats' => $hats])->onlyOne());
    }

    #[Test]
    public function it_refuses_to_pick_one_entry_out_of_several(): void
    {
        $registry = new FakeRegistry(['hats' => new stdClass, 'shoes' => new stdClass]);

        $this->expectException(NameRefused::class);
        $this->expectExceptionMessage('Name the shelf: 2 are declared (hats, shoes).');

        $registry->onlyOne();
    }

    #[Test]
    public function it_refuses_to_pick_one_entry_out_of_none(): void
    {
        $this->expectException(NameRefused::class);
        $this->expectExceptionMessage('Name the shelf: none is declared.');

        new FakeRegistry()->onlyOne();
    }

    #[Test]
    public function it_lists_the_names_in_the_order_they_were_declared(): void
    {
        $registry = new FakeRegistry(['shoes' => new stdClass, 'hats' => new stdClass]);

        $this->assertSame(['shoes', 'hats'], $registry->names());
        $this->assertSame([], new FakeRegistry()->names());
    }

    /** A registry that builds its entries lazily relies on this to build them before any read. */
    #[Test]
    public function it_prepares_its_entries_before_every_kind_of_lookup(): void
    {
        $registry = new FakeRegistry(['hats' => new stdClass]);

        $registry->named('hats');
        $registry->onlyOne();
        $registry->names();

        $this->assertSame(3, $registry->lookups);
    }
}
