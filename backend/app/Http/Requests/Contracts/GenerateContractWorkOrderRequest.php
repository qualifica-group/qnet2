<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\Enums\WorkOrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/contracts/{contract}/work-orders (spec
 * 0095, D-6/D-11). Deliberately carries NO `quote_id`: it is resolved
 * server-side from `$contract->quote_id` in the controller (constraint:
 * "quote_id della generazione viene dal contratto lato server, MAI dal
 * client"). `quote_line_ids`' membership invariant (belongs to the offer,
 * REVENUE-only, D-7 of spec 0093) and the "not already programmed"
 * invariant (D-4) are NOT checked here — both need a query against the real
 * rows, so they are enforced server-side by
 * App\Services\WorkOrders\WorkOrderLineWriter inside WorkOrderService's own
 * create() transaction (AC-032/AC-033).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller: `contracts.program` ANDed with the contract's lifecycle,
 * ContractActionAvailability::mayProgram()).
 */
class GenerateContractWorkOrderRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'type' => ['required', 'string', Rule::in(WorkOrderType::values())],
            // Spec 0096, D-5: neither may be empty on a commessa, so both
            // belong in this dialog under D-11's own rule ("exactly the
            // fields that cannot be left empty"). Partecipanti are NOT here:
            // they are assigned later from the work order's own form.
            'start_date' => ['required', 'date'],
            'supervisor_ids' => ['required', 'array', 'min:1'],
            'supervisor_ids.*' => ['integer', Rule::exists('users', 'id')],
            'quote_line_ids' => ['required', 'array', 'min:1'],
            'quote_line_ids.*' => ['integer'],
            // Spec 0098, AC-019: the "Programma" dialog carries its own
            // "Informazioni aggiuntive" section — same rule as
            // Store|UpdateWorkOrderRequest, validated per-code server-side by
            // WorkOrderAttributeValueWriter.
            'attribute_values' => ['sometimes', 'array'],
        ];
    }
}
