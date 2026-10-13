<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\Enums\WorkOrderType;
use App\Services\Contracts\ContractProgramBatch;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/contracts/{contract}/work-orders/batch (spec 0215,
 * D-1/D-9): the per-group fields of GenerateContractWorkOrderRequest under
 * `groups.*`. Line membership (belongs to the offer, REVENUE-only, not yet
 * programmed) stays in WorkOrderLineWriter; only the cross-group rule "a line
 * appears in at most one group" is checked here. Authorization stays in the
 * controller (`contracts.program` AND mayProgram()).
 */
class GenerateContractWorkOrdersBatchRequest extends FormRequest
{
    private const int TITLE_MAX = 191;

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
            'groups' => ['required', 'array', 'min:1', 'max:'.ContractProgramBatch::MAX_GROUPS],
            'groups.*.title' => ['nullable', 'string', 'max:'.self::TITLE_MAX],
            'groups.*.type' => ['required', 'string', Rule::in(WorkOrderType::values())],
            'groups.*.start_date' => ['required', 'date'],
            'groups.*.supervisor_ids' => ['required', 'array', 'min:1'],
            'groups.*.supervisor_ids.*' => ['integer', Rule::exists('users', 'id')],
            'groups.*.task_template_id' => ['nullable', 'integer', Rule::exists('task_templates', 'id')->where('is_active', true)],
            'groups.*.quote_line_ids' => ['required', 'array', 'min:1'],
            'groups.*.quote_line_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $seen = [];

            foreach ((array) $this->input('groups', []) as $index => $group) {
                foreach ((array) ($group['quote_line_ids'] ?? []) as $lineId) {
                    if (isset($seen[(int) $lineId])) {
                        $validator->errors()->add(
                            "groups.{$index}.quote_line_ids",
                            "Quote line #{$lineId} is already in another group.",
                        );

                        continue 2;
                    }
                }

                foreach ((array) ($group['quote_line_ids'] ?? []) as $lineId) {
                    $seen[(int) $lineId] = true;
                }
            }
        }];
    }
}
