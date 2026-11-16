<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\NameRefused;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeRegistry;
use stdClass;

it('hands back the entry under its name', function (): void {
    $shoes = new stdClass;
    $registry = new FakeRegistry(['hats' => new stdClass, 'shoes' => $shoes]);

    expect($registry->named('shoes'))->toBe($shoes);
});

it('names the declared entries when asked for an unknown one', function (): void {
    $registry = new FakeRegistry(['hats' => new stdClass, 'shoes' => new stdClass]);

    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('No shelf named "coats". Declared: hats, shoes.');

    $registry->named('coats');
});

it('says none is declared when asked for a name in an empty registry', function (): void {
    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('No shelf named "hats". Declared: none.');

    new FakeRegistry()->named('hats');
});

it('does not match a name written in another case', function (): void {
    $this->expectException(NameRefused::class);

    new FakeRegistry(['hats' => new stdClass])->named('Hats');
});

it('hands back the only entry without a name', function (): void {
    $hats = new stdClass;

    expect(new FakeRegistry(['hats' => $hats])->onlyOne())->toBe($hats);
});

it('refuses to pick one entry out of several', function (): void {
    $registry = new FakeRegistry(['hats' => new stdClass, 'shoes' => new stdClass]);

    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('Name the shelf: 2 are declared (hats, shoes).');

    $registry->onlyOne();
});

it('refuses to pick one entry out of none', function (): void {
    $this->expectException(NameRefused::class);
    $this->expectExceptionMessage('Name the shelf: none is declared.');

    new FakeRegistry()->onlyOne();
});

it('lists the names in the order they were declared', function (): void {
    $registry = new FakeRegistry(['shoes' => new stdClass, 'hats' => new stdClass]);

    expect($registry->names())->toBe(['shoes', 'hats']);
    expect(new FakeRegistry()->names())->toBe([]);
});

// A registry that builds its entries lazily relies on this to build them before any read.
it('prepares its entries before every kind of lookup', function (): void {
    $registry = new FakeRegistry(['hats' => new stdClass]);

    $registry->named('hats');
    $registry->onlyOne();
    $registry->names();

    expect($registry->lookups)->toBe(3);
});
