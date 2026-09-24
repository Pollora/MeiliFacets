<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\WordPressDefaultTerms;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WordPressDefaultTermsTest extends TestCase
{
    private const string TAXONOMY = 'meilifacets_probe';

    #[Test]
    public function it_looks_a_missing_default_term_up_once(): void
    {
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

        $this->assertSame(1, $reads);
    }
}
