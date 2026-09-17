<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Illuminate\Translation\MessageSelector;
use MessageFormatter;

final readonly class CountLabel
{
    private const string FALLBACK_LOCALE = 'en';

    /** Form 0 for the CLDR category `one`, form 1 for every other. */
    private const string FORM_OF_COUNT = '{n, plural, one{0} other{1}}';

    public string $locale;

    private ?MessageFormatter $formatter;

    public function __construct(private string $appLocale)
    {
        $this->locale = $this->tagOf($appLocale);
        $this->formatter = class_exists(MessageFormatter::class) ? new MessageFormatter($this->locale, self::FORM_OF_COUNT) : null;
    }

    public function of(string $pattern, int $count): string
    {
        $forms = explode('|', $pattern);

        return str_replace(':count', (string) $count, $forms[$this->formOf($count)] ?? $forms[0]);
    }

    private function formOf(int $count): int
    {
        if ($this->formatter instanceof MessageFormatter) {
            return (int) $this->formatter->format(['n' => $count]);
        }

        // Without `ext-intl`, Laravel's own table: it agrees with CLDR on the forms French and English use.
        return min(new MessageSelector()->getPluralIndex($this->appLocale, $count), 1);
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
