<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

use Collator;
use IntlException;

final class SiteCollator
{
    /** @var array<string, Collator|null> */
    private array $collators = [];

    public function current(): ?Collator
    {
        $locale = SiteLocale::current();

        if (! array_key_exists($locale, $this->collators)) {
            $this->collators[$locale] = $this->collatorFor($locale);
        }

        return $this->collators[$locale];
    }

    private function collatorFor(string $locale): ?Collator
    {
        if (! class_exists(Collator::class)) {
            return null;
        }

        try {
            $collator = new Collator($locale);
        } catch (IntlException) {
            return null;
        }

        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);

        return $collator;
    }
}
