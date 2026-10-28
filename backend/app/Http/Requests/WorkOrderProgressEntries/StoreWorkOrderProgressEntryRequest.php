<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderProgressEntries;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /api/work-orders/{workOrder}/progress-entries (spec 0321, R-2).
 * Authorization is checked here (and again in the controller) so a caller
 * without `update` never learns the current percentage through a 422 message.
 */
class StoreWorkOrderProgressEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('workOrder');

        return $workOrder instanceof WorkOrder && ($this->user()?->can('update', $workOrder) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'percentage' => ['required', 'integer', 'between:1,100'],
            'recorded_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((int) $this->input('percentage') <= $this->currentTotal()) {
                $validator->errors()->add('percentage', __('work_orders.progress.percentage_not_above_current'));
            }
        });
    }

    /**
     * The "% Stato attuale" typed by the user: the new TOTAL, not the increment.
     */
    public function percentage(): int
    {
        return (int) $this->validated('percentage');
    }

    public function recordedOn(): string
    {
        return (string) $this->validated('recorded_on');
    }

    public function currentTotal(): int
    {
        return (int) $this->workOrder()->progressEntries()->reorder()->sum('percentage');
    }

    private function workOrder(): WorkOrder
    {
        /** @var WorkOrder */
        return $this->route('workOrder');
    }
}
