<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderProgressEntries;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /api/work-orders/{workOrder}/progress-entries (spec 0321, D-6).
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

            $this->assertAboveCurrent($validator);
            $this->assertNotBeforeLatest($validator);
        });
    }

    public function percentage(): int
    {
        return (int) $this->validated('percentage');
    }

    public function recordedOn(): string
    {
        return (string) $this->validated('recorded_on');
    }

    private function assertAboveCurrent(Validator $validator): void
    {
        $current = (int) $this->workOrder()->progressEntries()->reorder()->max('percentage');

        if ((int) $this->input('percentage') <= $current) {
            $validator->errors()->add('percentage', __('work_orders.progress.percentage_not_above_current', ['current' => $current]));
        }
    }

    private function assertNotBeforeLatest(Validator $validator): void
    {
        $latest = $this->workOrder()->progressEntries()->reorder()->max('recorded_on');

        if ($latest !== null && (string) $this->input('recorded_on') < substr((string) $latest, 0, 10)) {
            $validator->errors()->add('recorded_on', __('work_orders.progress.recorded_on_before_latest', ['date' => substr((string) $latest, 0, 10)]));
        }
    }

    private function workOrder(): WorkOrder
    {
        /** @var WorkOrder */
        return $this->route('workOrder');
    }
}
