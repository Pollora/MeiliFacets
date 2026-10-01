<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\Contract;

final readonly class CardFieldAttribute
{
    private const array NAMES = [
        'href', 'src', 'srcset', 'sizes', 'alt', 'title', 'width', 'height', 'value', 'datetime',
    ];

    private const array URL_NAMES = ['href', 'src'];

    private const array URL_LIST_NAMES = ['srcset'];

    private const array DIMENSION_NAMES = ['width', 'height'];

    private const array PREFIXES = ['aria-', 'data-'];

    private const string PREFIX_WILDCARD = '*';

    private const string LIST_GLUE = ', ';

    private const string NAME_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    private const string DIMENSION_PATTERN = '/^[1-9][0-9]*$/';

    private const array SAFE_SCHEMES = ['http', 'https'];

    private const string SCHEME_PATTERN = '/^([a-z][a-z0-9+.-]*):/i';

    private const string URL_LIST_PATTERN = '/[\s,]+/';

    /** Browsers drop these inside a URL: `java\tscript:` runs as `javascript:`. */
    private const array IGNORED_IN_URL = ["\t", "\n", "\r"];

    /** `ComponentAttributeBag` trims every value it prints: the client trims the same characters. */
    private const string TRIMMED_AROUND_VALUE = " \t\n\r\0\x0B";

    /** Browsers strip C0 controls and spaces around a URL. */
    private const string TRIMMED_AROUND_URL = "\x00..\x20";

    private function __construct(public string $name) {}

    public static function named(string $name): self
    {
        if (! self::isAllowed($name)) {
            throw BindingRefused::attribute($name, self::allowedNames());
        }

        return new self($name);
    }

    private static function isAllowed(string $name): bool
    {
        if (self::isMalformed($name) || self::isReserved($name)) {
            return false;
        }

        return in_array($name, self::NAMES, true) || self::hasAllowedPrefix($name);
    }

    public static function written(string $value): string
    {
        return trim($value, self::TRIMMED_AROUND_VALUE);
    }

    /** An empty value is not written at all: `href=""` would link the current page. */
    public function accepts(string $value): bool
    {
        return match (true) {
            $value === '' => false,
            in_array($this->name, self::URL_NAMES, true) => $this->isSafeUrl($value),
            in_array($this->name, self::URL_LIST_NAMES, true) => $this->isSafeUrlList($value),
            in_array($this->name, self::DIMENSION_NAMES, true) => preg_match(self::DIMENSION_PATTERN, $value) === 1,
            default => true,
        };
    }

    private function isSafeUrl(string $url): bool
    {
        $read = trim(str_replace(self::IGNORED_IN_URL, '', $url), self::TRIMMED_AROUND_URL);

        if (preg_match(self::SCHEME_PATTERN, $read, $scheme) !== 1) {
            return true;
        }

        return in_array(strtolower($scheme[1]), self::SAFE_SCHEMES, true);
    }

    private function isSafeUrlList(string $list): bool
    {
        $urls = preg_split(self::URL_LIST_PATTERN, $list, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_all($urls, $this->isSafeUrl(...));
    }

    private static function allowedNames(): string
    {
        $prefixes = array_map(fn (string $prefix): string => $prefix.self::PREFIX_WILDCARD, self::PREFIXES);

        return implode(self::LIST_GLUE, [...self::NAMES, ...$prefixes]);
    }

    private static function isMalformed(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) !== 1;
    }

    private static function isReserved(string $name): bool
    {
        return str_starts_with($name, Contract::Attribute->value);
    }

    private static function hasAllowedPrefix(string $name): bool
    {
        return array_any(self::PREFIXES, fn (string $prefix): bool => str_starts_with($name, $prefix));
    }
}
