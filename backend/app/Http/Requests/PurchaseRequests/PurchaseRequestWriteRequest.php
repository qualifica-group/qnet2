<?php

namespace App\Http\Requests\PurchaseRequests;

use App\DataObjects\PurchaseRequests\PurchaseRequestData;
use App\Enums\HttpStatusEnum;
use App\Enums\PurchaseRequestPriority;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\PurchaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * PurchaseRequestWritePayload (spec 0208), shared by create (POST) and the full
 * edit (PUT). Authorization stays in the controller (PurchaseRequestPolicy);
 * the rules that need the database state (line immutability D-10, deletions
 * D-11) live in PurchaseRequestService. A closed request answers 409 before the
 * payload is validated, so the conflict is not masked by field errors.
 */
class PurchaseRequestWriteRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public const int TEXT_MAX_LENGTH = 5000;

    public const int MAX_LINES = 200;

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
            'subject' => ['required', 'string', 'max:255'],
            'requested_at' => ['required', 'date_format:Y-m-d'],
            'priority' => ['required', Rule::enum(PurchaseRequestPriority::class)],
            'requester_id' => ['required', 'integer', Rule::exists('users', 'id')],
            // Active users only; the manager already assigned stays valid even if deactivated since.
            'function_manager_id' => ['required', 'integer', Rule::exists('users', 'id')->where(
                fn ($users) => $users->where('is_active', true)->orWhere('id', $this->authorizationModel()?->getAttribute('function_manager_id')),
            )],
            'customer_id' => ['nullable', 'integer', Rule::exists('registries', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('registries', 'id')->where('is_supplier', true)],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => [
                'required', 'integer',
                Rule::exists('company_sites', 'id')->where('company_id', $this->input('company_id')),
            ],
            'operational_site_id' => ['required', 'integer', Rule::exists('operational_sites', 'id')],
            'business_function_id' => ['required', 'integer', Rule::exists('business_functions', 'id')],
            'notes' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'delivery_terms' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'procurement_plan' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'technical_requirements' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'special_conditions' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.reason' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'lines.*.unit_of_measure_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0', 'max:999999999999'],
            'lines.*.vat_rate_id' => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $purchaseRequest = $this->authorizationModel();

        if ($purchaseRequest instanceof PurchaseRequest && $purchaseRequest->isClosed() && $this->user()?->can('update', $purchaseRequest)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('This purchase request is closed and can no longer be changed.'),
            ], HttpStatusEnum::CONFLICT->value));
        }
    }

    protected function authorizationResource(): string
    {
        return 'purchase-requests';
    }

    protected function authorizationModel(): ?Model
    {
        $purchaseRequest = $this->route('purchaseRequest');

        return $purchaseRequest instanceof Model ? $purchaseRequest : null;
    }

    public function toData(): PurchaseRequestData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return PurchaseRequestData::fromValidated($validated);
    }
}
