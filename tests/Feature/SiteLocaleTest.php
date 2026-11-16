<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Collator;
use Locale;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Support\SiteCollator;
use Modules\MeiliFacets\Support\SiteLocale;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Modules\MeiliFacets\View\CountLabel;
use Tests\TestCase;

uses(TestCase::class, SwitchesTheSiteLocale::class);

it('labels counts in a language set after the labels were resolved', function (): void {
    $countLabel = $this->app->make(CountLabel::class);

    expect($this->underSiteLocale('de_DE', $countLabel->languageTag(...)))->toBe('de-DE');
    expect($this->underSiteLocale('de_DE', fn (): string => $countLabel->of(':count Ergebnis|:count Ergebnisse', 1)))->toBe('1 Ergebnis');
    expect($this->underSiteLocale('de_DE', fn (): string => $countLabel->of(':count Ergebnis|:count Ergebnisse', 2)))->toBe('2 Ergebnisse');
});

it('orders names in a language set after the order was resolved', function (): void {
    $order = $this->app->make(NameOrder::class);
    $zebra = new FacetValue('zebra', 'zebra', 1, false, false);
    $apple = new FacetValue('apple', 'äpple', 1, false, false);

    expect($this->underSiteLocale('sv_SE', fn (): int => $order->compare($zebra, $apple)))->toBeLessThan(0);
    expect($this->underSiteLocale('de_DE', fn (): int => $order->compare($zebra, $apple)))->toBeGreaterThan(0);
});

it('keeps one collator per language', function (): void {
    $collators = $this->app->make(SiteCollator::class);

    $german = $this->underSiteLocale('de_DE', $collators->current(...));

    expect($german)->toBeInstanceOf(Collator::class);
    expect(Locale::getPrimaryLanguage($german->getLocale(Locale::VALID_LOCALE)))->toBe('de');
    expect($this->underSiteLocale('de_DE', $collators->current(...)))->toBe($german);
});

it('speaks laravels language when wordpress names none', function (): void {
    expect($this->underSiteLocale('', SiteLocale::current(...)))->toBe($this->app->getLocale());
});
