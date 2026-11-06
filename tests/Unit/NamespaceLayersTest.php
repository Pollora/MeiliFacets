<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * `R-252`: each top-level namespace only reaches the ones below it. The contracts name the domain's objects and the
 * domain takes contracts, so they count as one layer.
 */
final class NamespaceLayersTest extends TestCase
{
    private const string ROOT = 'Modules\\MeiliFacets\\';

    /** Bottom first; a rank shared by two namespaces makes them one layer. Providers wire every layer and stand outside. */
    private const array LAYERS = [
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

    /** Kept on purpose: the default presentation is an enum, and `app/Enums` stays flat (`R-252`). */
    private const array TOLERATED = [
        'Enums\\Presentation -> Contracts\\ValuePresentation',
    ];

    #[Test]
    public function every_namespace_reaches_only_the_ones_below_it(): void
    {
        $this->assertSame([], array_values(array_diff($this->risingReferences(), self::TOLERATED)));
    }

    #[Test]
    public function every_tolerated_reference_is_still_there(): void
    {
        $this->assertSame([], array_values(array_diff(self::TOLERATED, $this->risingReferences())));
    }

    /**
     * @return list<string> `Layer\Class -> Layer\Class`, for every reference that does not point down
     */
    private function risingReferences(): array
    {
        $rising = [];

        foreach ($this->sources() as $file) {
            $from = $this->classOf($file);

            foreach ($this->referencesIn($file) as $to) {
                if ($this->rises($from, $to)) {
                    $rising[] = $from.' -> '.$to;
                }
            }
        }

        return array_values(array_unique($rising));
    }

    private function rises(string $from, string $to): bool
    {
        $fromLayer = self::LAYERS[strtok($from, '\\')] ?? null;
        $toLayer = self::LAYERS[strtok($to, '\\')] ?? null;

        return $fromLayer !== null && $toLayer !== null && $toLayer > $fromLayer;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function sources(): array
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

    private function classOf(SplFileInfo $file): string
    {
        $relative = substr($file->getPathname(), strlen(dirname(__DIR__, 2).'/app/'));

        return str_replace(['/', '.php'], ['\\', ''], $relative);
    }

    /**
     * @return list<string> referenced classes, relative to the module's root namespace
     */
    private function referencesIn(SplFileInfo $file): array
    {
        $quoted = preg_quote(self::ROOT, '/');
        preg_match_all('/'.$quoted.'((?:[A-Za-z0-9_]+\\\\)*[A-Za-z0-9_]+)/', (string) file_get_contents($file->getPathname()), $matches);

        return array_values(array_unique($matches[1]));
    }
}
