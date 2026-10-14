<?php

namespace App\Http\Requests\PurchaseOrders;

use App\DataObjects\PurchaseOrders\PurchaseOrderData;
use App\Enums\HttpStatusEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * PurchaseOrderWritePayload (spec 0210), shared by create (POST, which also
 * accepts `copies`) and the edit (PUT/PATCH), partial since spec 0257 D-1:
 * every key is `sometimes` and the company/site check reads the stored
 * company when it is not sent. Authorization stays in the
 * controller (PurchaseOrderPolicy); the rules that need the database state
 * (lines of another order) live in PurchaseOrderService. An order that is no
 * longer editable answers 409 before the payload is validated, so the conflict
 * is not masked by field errors (D-10).
 */
class PurchaseOrderWriteRequest extends FormRequest
{
    use EnforcesFieldPermissions {
        currentFieldValue as private traitCurrentFieldValue;
    }

    public const int TEXT_MAX_LENGTH = 5000;

    public const int MAX_LINES = 200;

    public const int MIN_COPIES = 1;

    public const int MAX_COPIES = 12;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'supplier_id' => ['required', 'integer', Rule::exists('registries', 'id')->where('is_supplier', true)],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => [
                'required', 'integer',
                Rule::exists('company_sites', 'id')->where('company_id', $this->has('company_id') ? $this->input('company_id') : $this->purchaseOrder()?->company_id),
            ],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
            'general_notes' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'is_internal_user' => ['nullable', 'boolean'],
            'customer_id' => ['nullable', 'integer', Rule::exists('registries', 'id')],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')],
            'quote_id' => ['nullable', 'integer', Rule::exists('quotes', 'id')],
            'opportunity_id' => ['nullable', 'integer', Rule::exists('opportunities', 'id')],
            'sector_ids' => ['sometimes', 'array'],
            'sector_ids.*' => ['integer', 'distinct', Rule::exists('sectors', 'id')],
            'sector_notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.cost_type_id' => ['required', 'integer', $this->costTypeRule()],
            'lines.*.unit_of_measure_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0', 'max:999999999999'],
            'lines.*.vat_rate_id' => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
            'lines.*.work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')],
        ];

        if ($this->purchaseOrder() === null) {
            $rules['copies'] = ['nullable', 'integer', 'between:'.self::MIN_COPIES.','.self::MAX_COPIES];

            return $rules;
        }

        // Edit: a partial PATCH (spec 0257, D-1); a line, once sent, keeps its own rules.
        foreach ($rules as $key => $keyRules) {
            if (! str_starts_with($key, 'lines.') && ! str_starts_with($key, 'sector_ids.') && $keyRules[0] !== 'sometimes') {
                $rules[$key] = ['sometimes', ...$keyRules];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $order = $this->purchaseOrder();

        if ($order !== null && ! $order->status->isEditable() && $this->user()?->can('update', $order)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('This purchase order can no longer be changed.'),
            ], HttpStatusEnum::CONFLICT->value));
        }
    }

    protected function authorizationResource(): string
    {
        return 'purchase-orders';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->purchaseOrder();
    }

    /**
     * `sector_ids` is not a column: its persisted value is the set of sector ids.
     */
    protected function currentFieldValue(?Model $model, string $field): mixed
    {
        if ($field === 'sector_ids' && $model instanceof PurchaseOrder) {
            return $model->sectors()->pluck('sectors.id')->map(fn (mixed $id): int => (int) $id)->all();
        }

        return $this->traitCurrentFieldValue($model, $field);
    }

    public function toData(): PurchaseOrderData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return PurchaseOrderData::fromValidated($validated);
    }

    private function purchaseOrder(): ?PurchaseOrder
    {
        $order = $this->route('purchaseOrder');

        return $order instanceof PurchaseOrder ? $order : null;
    }

    /**
     * Active cost types only; the one a stored line already uses stays valid
     * even if it was deactivated since.
     */
    private function costTypeRule(): mixed
    {
        $kept = $this->purchaseOrder()?->lines()->pluck('cost_type_id')->all() ?? [];

        return Rule::exists('cost_types', 'id')->where(
            fn ($costTypes) => $costTypes->where('is_active', true)->orWhereIn('id', $kept),
        );
    }
}
