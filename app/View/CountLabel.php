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

    /**
     * @param  Closure(): string  $siteLocale
     */
    public function __construct(private readonly Closure $siteLocale) {}

    /** `Intl.PluralRules` throws on tags ICU tolerates, `pt_PT_ao90` or `C`: only the language and its region travel. */
    public function locale(): string
    {
        if (preg_match('/^([a-z]{2,3})(?:[_-]([A-Z]{2}))?(?:[_-]|$)/', ($this->siteLocale)(), $parts) !== 1) {
            return self::FALLBACK_LOCALE;
        }

        return isset($parts[2]) ? $parts[1].'-'.$parts[2] : $parts[1];
    }

    public function of(string $pattern, int $count): string
    {
        $forms = explode('|', $pattern);

        return str_replace(':count', (string) $count, $forms[$this->formOf($count)] ?? $forms[0]);
    }

    private function formOf(int $count): int
    {
        if (! class_exists(MessageFormatter::class)) {
            return min(new MessageSelector()->getPluralIndex(str_replace('-', '_', $this->locale()), $count), 1);
        }

        $locale = $this->locale();
        $this->formatters[$locale] ??= new MessageFormatter($locale, self::FORM_OF_COUNT);

        return (int) $this->formatters[$locale]->format(['n' => $count]);
    }
}
