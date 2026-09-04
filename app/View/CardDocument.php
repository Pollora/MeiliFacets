<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\CardField;

/**
 * Reads one projected card. The index is a store of its own: a field can be
 * missing, or hold something other than what the projector wrote.
 */
final readonly class CardDocument
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function __construct(private array $fields = []) {}

    public function text(CardField $field): string
    {
        $value = $this->fields[$field->value] ?? '';

        return is_string($value) ? $value : '';
    }

    public function size(CardField $field): ?int
    {
        $value = $this->fields[$field->value] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
