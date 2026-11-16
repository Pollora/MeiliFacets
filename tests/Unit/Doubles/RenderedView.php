<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Illuminate\Contracts\View\View;

/** What `view()` hands a component's `render()`: the view's name and data, never rendered. */
final class RenderedView implements View
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly string $name, private array $data = []) {}

    public function name(): string
    {
        return $this->name;
    }

    public function with($key, $value = null): static
    {
        $this->data = [...$this->data, ...(is_array($key) ? $key : [$key => $value])];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function render(): string
    {
        return '';
    }
}
