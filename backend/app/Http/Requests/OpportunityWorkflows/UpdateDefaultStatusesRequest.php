<?php

declare(strict_types=1);

namespace App\Http\Requests\OpportunityWorkflows;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT /api/opportunity-workflows/default-statuses
 * (spec 0047 Lane A): the GLOBAL default status set's custom rows (the same
 * shape as a workflow's own `statuses`, minus the workflow-scoping — this
 * endpoint always targets `opportunity_workflow_id` null). `statuses.*.id`
 * optional: present = update an existing row (custom OR system — a system
 * row accepts every descriptive field but never a `group` change, enforced
 * at the WorkflowStatusWriter layer),
 * absent = a new custom row. `statuses.*.system_key` carries the OPTIONAL
 * 'validated' mark only (user directive 2026-08-03).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('opportunity-workflows.update')).
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
            // Each row's own system key. Only 'validated' is HONORED (it is
            // the optional mark the writer applies); the mandatory rows are
            // identified by their persisted `id`, so their key is echoed back
            // and ignored.
            'statuses.*.system_key' => ['sometimes', 'nullable', Rule::enum(WorkflowStatusSystemKey::class)],
        ];
    }

    /**
     * `system_key_submitted` preserves the absent/explicitly-null distinction
     * a plain nullable string can't express (mirrors
     * UpdateOpportunityWorkflowData): only a client that actually SENT the
     * key may remove the optional 'validated' mark.
     *
     * @return array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string, system_key_submitted: bool}>
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
                'system_key' => isset($status['system_key']) ? (string) $status['system_key'] : null,
                'system_key_submitted' => array_key_exists('system_key', $status),
            ],
            $statuses,
        );
    }
}
