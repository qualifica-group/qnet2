<?php

namespace App\Http\Requests\WorkOrders;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Http\Requests\Concerns\ValidatesStandaloneWorkOrderReferences;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;

/**
 * Validates POST /api/project-work-orders (spec 0431): a project commessa
 * born without any offer. The same fields as POST /api/work-orders minus the
 * offer, its lines, the type (always `project`) and the forced closure, plus
 * the own client, company, sites and business-function lines (D-2/D-8). The
 * title is required: without lines the automatic one would be just the code.
 *
 * Authorization stays in the controller (WorkOrderPolicy::create), as for the
 * parent request.
 */
class StoreStandaloneProjectWorkOrderRequest extends StoreWorkOrderRequest
{
    use ValidatesStandaloneWorkOrderReferences;

    /** Offer-only or closure keys: never part of this payload. */
    private const array EXCLUDED_KEYS = ['quote_id', 'quote_line_ids', 'quote_line_ids.*', 'type', 'is_force_closed', 'force_close_reason', 'force_close_status_id'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), self::EXCLUDED_KEYS),
            'title' => ['required', 'string', 'max:191'],
            ...$this->standaloneReferenceRules(required: true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $this->validateStandaloneReferences($validator);
        });
    }

    public function toData(): CreateWorkOrderData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateWorkOrderData::forStandaloneProject($validated);
    }
}
