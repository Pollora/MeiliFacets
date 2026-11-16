<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Illuminate\Container\Container;
use LogicException;

/**
 * The WordPress and Laravel helpers a class under test reaches for, answered from here.
 * The global functions that read it live in `wordpress-functions.php` and are only ever loaded by a test
 * that runs in its own process: defining them in the shared one would change what `function_exists()` answers
 * for every other test.
 */
final class WordPress
{
    /** @var array<string, callable> */
    private static array $handlers = [];

    /** @var array<string, int> */
    public static array $calls = [];

    /** @var list<array{string, array<int, mixed>}> every call, in order */
    public static array $log = [];

    /** @var array<string, mixed> */
    public static array $config = [];

    /** @var array<string, mixed> */
    public static array $query = [];

    public static string $queryString = '';

    public static function boot(): void
    {
        require_once __DIR__.'/wordpress-functions.php';

        self::reset();
        Container::setInstance(new Application);
    }

    public static function reset(): void
    {
        self::$handlers = [];
        self::$calls = [];
        self::$log = [];
        self::$config = [];
        self::$query = [];
        self::$queryString = '';
        unset($GLOBALS['wp'], $GLOBALS['wp_rewrite']);
    }

    /** A function answering with a fixed value, or with what the callable returns for its arguments. */
    public static function fake(string $function, mixed $answer): void
    {
        self::$handlers[$function] = is_callable($answer) ? $answer : static fn (): mixed => $answer;
    }

    public static function has(string $function): bool
    {
        return isset(self::$handlers[$function]);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function call(string $function, array $arguments, ?callable $default = null): mixed
    {
        self::$calls[$function] = (self::$calls[$function] ?? 0) + 1;
        self::$log[] = [$function, $arguments];

        if (isset(self::$handlers[$function])) {
            return (self::$handlers[$function])(...$arguments);
        }

        return $default !== null ? $default(...$arguments) : throw new LogicException("WordPress function {$function}() was not faked.");
    }
}
