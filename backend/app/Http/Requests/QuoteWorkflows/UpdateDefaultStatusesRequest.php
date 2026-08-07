<?php

declare(strict_types=1);

namespace App\Http\Requests\QuoteWorkflows;

use App\Enums\WorkflowStatusGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT /api/quote-workflows/default-statuses (spec
 * 0047, moved onto the Offerta by spec 0083 D-6): the GLOBAL default status
 * set's custom rows (the same shape as a workflow's own `statuses`, minus
 * the workflow-scoping — this endpoint always targets `quote_workflow_id`
 * null). `statuses.*.id` optional: present = update an existing row (custom
 * OR system — a system row accepts every descriptive field but never a
 * `group` change, enforced at the WorkflowStatusWriter layer), absent = a
 * new custom row. No `system_key` is accepted: a set's system rows are
 * created with it and identified by their persisted `id`, and no key can be
 * claimed by a payload (user directive 2026-08-07).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('quote-workflows.update')).
 */
class UpdateDefaultStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'statuses' => ['required', 'array'],
            'statuses.*.id' => ['sometimes', 'integer'],
            'statuses.*.name' => ['required', 'string', 'max:191'],
            'statuses.*.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'statuses.*.color' => ['nullable', 'string', 'max:32'],
            'statuses.*.group' => ['required', Rule::enum(WorkflowStatusGroup::class)],
            'statuses.*.requires_note' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>
     */
    public function statuses(): array
    {
        /** @var array<int, array<string, mixed>> $statuses */
        $statuses = $this->validated('statuses');

        return array_map(
            static fn (array $status): array => [
                'id' => isset($status['id']) ? (int) $status['id'] : null,
                'name' => (string) $status['name'],
                'description' => array_key_exists('description', $status) ? $status['description'] : null,
                'color' => array_key_exists('color', $status) ? $status['color'] : null,
                'group' => (string) $status['group'],
                'requires_note' => (bool) ($status['requires_note'] ?? false),
            ],
            $statuses,
        );
    }
}
