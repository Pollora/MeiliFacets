<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\NamespaceLayers;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

// `R-252`: each top-level namespace only reaches the ones below it. The contracts name the domain's objects and the
// domain takes contracts, so they count as one layer.

const ROOT = 'Modules\\MeiliFacets\\';

const LAYERS = [
    'Enums' => 1,
    'Support' => 2,
    'Contracts' => 3,
    'Listing' => 3,
    'Discovery' => 4,
    'Search' => 5,
    'Indexing' => 6,
    'SiteSearch' => 7,
    'Http' => 8,
    'Seo' => 9,
    'Console' => 10,
    'View' => 11,
];

const TOLERATED = [
    'Enums\\Presentation -> Contracts\\ValuePresentation',
];

/**
 * @return list<string> `Layer\Class -> Layer\Class`, for every reference that does not point down
 */
function risingReferences(): array
{
    $rising = [];

    foreach (sources() as $file) {
        $from = classOf($file);

        foreach (referencesIn($file) as $to) {
            if (rises($from, $to)) {
                $rising[] = $from.' -> '.$to;
            }
        }
    }

    return array_values(array_unique($rising));
}

function rises(string $from, string $to): bool
{
    $fromLayer = LAYERS[strtok($from, '\\')] ?? null;
    $toLayer = LAYERS[strtok($to, '\\')] ?? null;

    return $fromLayer !== null && $toLayer !== null && $toLayer > $fromLayer;
}

/**
 * @return list<SplFileInfo>
 */
function sources(): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        dirname(__DIR__, 2).'/app',
        FilesystemIterator::SKIP_DOTS,
    ));

    return array_values(array_filter(
        iterator_to_array($files, false),
        static fn (SplFileInfo $file): bool => $file->getExtension() === 'php',
    ));
}

function classOf(SplFileInfo $file): string
{
    $relative = substr($file->getPathname(), strlen(dirname(__DIR__, 2).'/app/'));

    return str_replace(['/', '.php'], ['\\', ''], $relative);
}

/**
 * @return list<string> referenced classes, relative to the module's root namespace
 */
function referencesIn(SplFileInfo $file): array
{
    $quoted = preg_quote(ROOT, '/');
    preg_match_all('/'.$quoted.'((?:[A-Za-z0-9_]+\\\\)*[A-Za-z0-9_]+)/', (string) file_get_contents($file->getPathname()), $matches);

    return array_values(array_unique($matches[1]));
}

test('every namespace reaches only the ones below it', function (): void {
    expect(array_values(array_diff(risingReferences(), TOLERATED)))->toBe([]);
});

test('every tolerated reference is still there', function (): void {
    expect(array_values(array_diff(TOLERATED, risingReferences())))->toBe([]);
});
