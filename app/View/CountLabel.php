<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Closure;
use Illuminate\Translation\MessageSelector;
use MessageFormatter;

final class CountLabel
{
    private const string FALLBACK_LOCALE = 'en';

    private const string FORM_OF_COUNT = '{n, plural, one{0} other{1}}';

    /** @var array<string, MessageFormatter> */
    private array $formatters = [];

    /** @var array<string, string> */
    private array $tags = [];

    /**
     * @param  Closure(): string  $siteLocale
     */
    public function __construct(private readonly Closure $siteLocale) {}

    public function languageTag(): string
    {
        $locale = ($this->siteLocale)();

        return $this->tags[$locale] ??= $this->tagOf($locale);
    }

    public function of(string $pattern, int $count): string
    {
        $forms = explode('|', $pattern);

        return str_replace(':count', (string) $count, $forms[$this->formOf($count)] ?? $forms[0]);
    }

    private function formOf(int $count): int
    {
        $tag = $this->languageTag();

        if (! class_exists(MessageFormatter::class)) {
            // Laravel's table agrees with CLDR on French and English, not on 111 other locales.
            return min(new MessageSelector()->getPluralIndex(str_replace('-', '_', $tag), $count), 1);
        }

        $this->formatters[$tag] ??= new MessageFormatter($tag, self::FORM_OF_COUNT);

        return (int) $this->formatters[$tag]->format(['n' => $count]);
    }

    /** `Intl.PluralRules` throws on tags ICU tolerates, `pt_PT_ao90` or `C`: only the language and its region travel. */
    private function tagOf(string $locale): string
    {
        if (preg_match('/^([a-z]{2,3})(?:[_-]([A-Z]{2}))?(?:[_-]|$)/', $locale, $parts) !== 1) {
            return self::FALLBACK_LOCALE;
        }

        return isset($parts[2]) ? $parts[1].'-'.$parts[2] : $parts[1];
    }
}
