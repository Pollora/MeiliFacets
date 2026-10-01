<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

use RuntimeException;

/**
 * @template TEntry of object
 */
abstract class NamedRegistry
{
    /** @var array<string, TEntry> */
    protected array $entries = [];

    /** What an entry is called in a diagnostic: `listing`, `search`. */
    abstract protected function kind(): string;

    /**
     * @return TEntry
     */
    public function named(string $name): object
    {
        return $this->entries[$name] ?? throw new RuntimeException(
            "No {$this->kind()} named \"{$name}\". Declared: {$this->listed()}."
        );
    }

    /**
     * The entry a template means when it names none. Ambiguity is refused
     * rather than guessed: a second one changes what the first one shows.
     *
     * @return TEntry
     */
    public function sole(): object
    {
        if (count($this->entries) === 1) {
            return reset($this->entries);
        }

        throw new RuntimeException("Name the {$this->kind()}: {$this->declared()}.");
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->entries);
    }

    private function declared(): string
    {
        return $this->entries === []
            ? 'none is declared'
            : count($this->entries)." are declared ({$this->listed()})";
    }

    private function listed(): string
    {
        return $this->entries === [] ? 'none' : implode(', ', $this->names());
    }
}
