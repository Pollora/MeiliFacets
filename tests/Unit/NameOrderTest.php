<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Collator;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\NameOrder;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NameOrderTest extends TestCase
{
    #[Test]
    #[RequiresPhpExtension('intl')]
    public function it_reads_accents_as_the_language_does(): void
    {
        $this->assertSame(
            ['Démaquillants', 'Déodorants', 'Diffuseurs', 'Dissolvants', 'Écrans', 'Épilation', 'Exfoliants'],
            $this->ordered(
                ['Diffuseurs', 'Démaquillants', 'Dissolvants', 'Déodorants', 'Exfoliants', 'Écrans', 'Épilation'],
                $this->collator()
            )
        );
    }

    /** Without `NUMERIC_COLLATION`, ICU reads the digits as text and puts `100ml` first. */
    #[Test]
    #[RequiresPhpExtension('intl')]
    public function it_reads_a_number_as_a_number(): void
    {
        $this->assertSame(['9ml', '10ml', '100ml'], $this->ordered(['100ml', '9ml', '10ml'], $this->collator()));
    }

    /** A label that is not valid UTF-8 makes `Collator::compare()` answer `false`, which is not an order. */
    #[Test]
    #[RequiresPhpExtension('intl')]
    public function it_orders_what_the_collator_refuses_to_read(): void
    {
        $broken = "\xC3\x28";

        $this->assertSame(['a', $broken], $this->ordered([$broken, 'a'], $this->collator()));
    }

    #[Test]
    public function it_falls_back_on_a_host_without_the_extension(): void
    {
        $this->assertSame(['9ml', '10ml', '100ml'], $this->ordered(['100ml', '9ml', '10ml'], null));
    }

    private function collator(): Collator
    {
        $collator = new Collator('fr_FR');
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);

        return $collator;
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function ordered(array $labels, ?Collator $collator): array
    {
        $values = array_map(
            static fn (string $label): FacetValue => new FacetValue($label, $label, 1, false, false),
            $labels
        );

        usort($values, new NameOrder($collator)->compare(...));

        return array_map(static fn (FacetValue $value): string => $value->label, $values);
    }
}
