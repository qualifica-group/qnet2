<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskTemplates\Concerns;

use App\Rules\TaskTemplateItemStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared `items.*` rules for Store/UpdateTaskTemplateRequest (spec 0124,
 * D-1): the row shape is IDENTICAL between the two, only whether `items` is
 * required and whether `items.*.id` may be present differ — mirroring
 * QuoteWorkflows\Concerns\ValidatesWorkflowCriteria's own statusesRules()
 * split (`$required`/`$allowIds`).
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesTaskTemplateItems
{
    private const int TITLE_MAX = 191;

    private const int ESTIMATED_MINUTES_MAX = 1_000_000;

    private const int DUE_OFFSET_DAYS_MAX = 3650;

    private const int ITEMS_MAX = 100;

    /**
     * @param  bool  $allowIds  update passes true (`items.*.id` identifies an
     *                          existing row, ownership asserted separately —
     *                          see UpdateTaskTemplateRequest); store passes
     *                          false (`items.*.id` is `prohibited`, D-1).
     * @return array<string, array<int, mixed>>
     */
    protected function itemsRules(bool $required, bool $allowIds): array
    {
        return [
            'items' => $required
                ? ['required', 'array', 'min:1', 'max:'.self::ITEMS_MAX]
                : ['sometimes', 'array', 'min:1', 'max:'.self::ITEMS_MAX],
            'items.*.id' => $allowIds ? ['sometimes', 'nullable', 'integer'] : ['prohibited'],
            'items.*.title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'items.*.description' => ['sometimes', 'nullable', 'string'],
            'items.*.estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::ESTIMATED_MINUTES_MAX],
            'items.*.task_status_id' => ['sometimes', 'nullable', 'integer', new TaskTemplateItemStatus],
            'items.*.due_offset_days' => ['required', 'integer', 'min:0', 'max:'.self::DUE_OFFSET_DAYS_MAX],
        ];
    }
}
