<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderWorkflows;

use App\Http\Requests\WorkOrderWorkflows\Concerns\ValidatesWorkOrderWorkflowPayload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PUT /api/work-order-workflows/default-statuses (spec 0322): the
 * GLOBAL default status set (`work_order_workflow_id` null), same row shape as
 * a workflow's own `statuses`. `statuses.*.id` present = update an existing
 * row (custom or system), absent = new custom row. No `system_key` is read
 * from the payload: system rows are identified by their persisted `id`.
 * Authorization stays in the controller.
 */
class UpdateDefaultStatusesRequest extends FormRequest
{
    use ValidatesWorkOrderWorkflowPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->statusesRules(allowIds: true);
        unset($rules['statuses.*.system_key']);
        $rules['statuses'] = ['required', 'array'];

        return $rules;
    }

    /**
     * @return array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>
     */
    public function statuses(): array
    {
        /** @var array<int, array<string, mixed>> $statuses */
        $statuses = $this->validated('statuses');

        return array_map(
            static fn (array $status): array => [
                'id' => isset($status['id']) ? (int) $status['id'] : null,
                'name' => (string) $status['name'],
                'description' => $status['description'] ?? null,
                'color' => $status['color'] ?? null,
                'group' => (string) $status['group'],
                'progress_percentage' => isset($status['progress_percentage']) ? (int) $status['progress_percentage'] : null,
            ],
            $statuses,
        );
    }
}
