<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/work-orders/{workOrder}/task-template/reload (spec
 * 0399): the active Modello di Task to (re)load. Authorization stays in the
 * controller (WorkOrderPolicy::update).
 */
class ReloadWorkOrderTaskTemplateRequest extends FormRequest
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
            'task_template_id' => ['required', 'integer', Rule::exists('task_templates', 'id')->where('is_active', true)],
        ];
    }

    public function taskTemplateId(): int
    {
        return (int) $this->validated('task_template_id');
    }
}
