<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Collator;
use Locale;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Support\SiteCollator;
use Modules\MeiliFacets\Support\SiteLocale;
use Modules\MeiliFacets\View\CountLabel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SiteLocaleTest extends TestCase
{
    use SwitchesTheSiteLocale;

    #[Test]
    public function it_labels_counts_in_a_language_set_after_the_labels_were_resolved(): void
    {
        $countLabel = $this->app->make(CountLabel::class);

        $this->assertSame('de-DE', $this->underSiteLocale('de_DE', $countLabel->languageTag(...)));
        $this->assertSame('1 Ergebnis', $this->underSiteLocale('de_DE', fn (): string => $countLabel->of(':count Ergebnis|:count Ergebnisse', 1)));
        $this->assertSame('2 Ergebnisse', $this->underSiteLocale('de_DE', fn (): string => $countLabel->of(':count Ergebnis|:count Ergebnisse', 2)));
    }

    #[Test]
    public function it_orders_names_in_a_language_set_after_the_order_was_resolved(): void
    {
        $order = $this->app->make(NameOrder::class);
        $zebra = new FacetValue('zebra', 'zebra', 1, false, false);
        $apple = new FacetValue('apple', 'äpple', 1, false, false);

        $this->assertLessThan(0, $this->underSiteLocale('sv_SE', fn (): int => $order->compare($zebra, $apple)));
        $this->assertGreaterThan(0, $this->underSiteLocale('de_DE', fn (): int => $order->compare($zebra, $apple)));
    }

    #[Test]
    public function it_keeps_one_collator_per_language(): void
    {
        $collators = $this->app->make(SiteCollator::class);

        $german = $this->underSiteLocale('de_DE', $collators->current(...));

        $this->assertInstanceOf(Collator::class, $german);
        $this->assertSame('de', Locale::getPrimaryLanguage($german->getLocale(Locale::VALID_LOCALE)));
        $this->assertSame($german, $this->underSiteLocale('de_DE', $collators->current(...)));
    }

    #[Test]
    public function it_speaks_laravels_language_when_wordpress_names_none(): void
    {
        $this->assertSame($this->app->getLocale(), $this->underSiteLocale('', SiteLocale::current(...)));
    }
}
