<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

use Symfony\Component\Finder\Finder;

/** `Modules/` sits outside the docroot, so the browser reads a copy of these. */
final readonly class PublishedAssets
{
    public const string COMMAND = 'php artisan module:publish MeiliFacets';

    private const string SOURCE = 'resources/assets';

    private const string PUBLISHED = 'modules/meilifacets';

    /**
     * An absent copy is reported like an outdated one: both leave the browser
     * reading rules the module no longer holds.
     *
     * @return list<string>
     */
    public function stale(): array
    {
        $source = module_path('MeiliFacets', self::SOURCE);

        if (! is_dir($source)) {
            return [];
        }

        $stale = [];

        foreach (Finder::create()->files()->in($source) as $file) {
            $path = $file->getRelativePathname();
            $published = public_path(self::PUBLISHED.'/'.$path);

            if (! is_file($published) || filemtime($published) < $file->getMTime()) {
                $stale[] = $path;
            }
        }

        return $stale;
    }
}
