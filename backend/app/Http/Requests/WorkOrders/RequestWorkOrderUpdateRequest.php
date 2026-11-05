<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrders;

use App\DataObjects\WorkOrders\RequestWorkOrderUpdateData;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderUpdateRequester;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/work-orders/{workOrder}/request-update
 * (spec 0352, modelled on RequestTaskUpdateRequest).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('requestUpdate', $workOrder)).
 *
 * "At least one recipient" is asserted HERE against the work order's CURRENT
 * pivots: the endpoint writes nothing, so there is no resulting record for a
 * Service-level guard to evaluate.
 */
class RequestWorkOrderUpdateRequest extends FormRequest
{
    private const int MESSAGE_MIN = 3;

    private const int MESSAGE_MAX = 2000;

    public function authorize(): bool
    {
        // Authorization handled in the controller via WorkOrderPolicy::requestUpdate.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', Rule::in(WorkOrderUpdateRequester::TARGETS)],
            'message' => ['required', 'string', 'min:'.self::MESSAGE_MIN, 'max:'.self::MESSAGE_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $target = $this->input('target');

            if (! is_string($target) || ! in_array($target, WorkOrderUpdateRequester::TARGETS, true)) {
                return;
            }

            if (app(WorkOrderUpdateRequester::class)->recipientIdsFor($this->workOrder(), $target) === []) {
                $validator->errors()->add('target', __('There is no recipient for this target.'));
            }
        });
    }

    private function workOrder(): WorkOrder
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $workOrder;
    }

    public function toData(): RequestWorkOrderUpdateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return RequestWorkOrderUpdateData::fromValidated($validated);
    }
}
