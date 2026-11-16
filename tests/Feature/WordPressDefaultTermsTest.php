<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\WordPressDefaultTerms;
use Tests\TestCase;

trait WordPressDefaultTermsSupport
{
    private const string TAXONOMY = 'meilifacets_probe';
}

uses(TestCase::class, WordPressDefaultTermsSupport::class);

it('looks a missing default term up once', function (): void {
    $reads = 0;
    $count = static function (mixed $value) use (&$reads): mixed {
        $reads++;

        return $value;
    };
    add_filter('pre_option_default_term_'.self::TAXONOMY, $count);

    try {
        $terms = new WordPressDefaultTerms;
        $terms->slugOf(self::TAXONOMY);
        $terms->slugOf(self::TAXONOMY);
    } finally {
        remove_filter('pre_option_default_term_'.self::TAXONOMY, $count);
    }

    expect($reads)->toBe(1);
});
