<?php

namespace App\Http\Requests\PurchaseRequests;

use App\DataObjects\PurchaseRequests\PurchaseRequestData;
use App\Enums\HttpStatusEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\BusinessFunction;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Rules\ActiveBusinessFunction;
use App\Services\PurchaseRequests\PurchaseRequestApproverResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * PurchaseRequestWritePayload (spec 0208), shared by create (POST) and edit
 * (PUT/PATCH). The edit is partial (spec 0256, D-1): every key is `sometimes`,
 * an absent one keeps its stored value, and the cross-checks read the submitted
 * value or, when absent, the stored one. Authorization stays in the controller (PurchaseRequestPolicy);
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
        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'requested_at' => ['required', 'date_format:Y-m-d'],
            // Active priorities only; the one already assigned stays valid even if deactivated since.
            'purchase_request_priority_id' => ['required', 'integer', Rule::exists('purchase_request_priorities', 'id')->where(
                fn ($priorities) => $priorities->where('is_active', true)->orWhere('id', $this->authorizationModel()?->getAttribute('purchase_request_priority_id')),
            )],
            'requester_id' => ['required', 'integer', Rule::exists('users', 'id')],
            // Active users only; the manager already assigned stays valid even if deactivated since.
            'function_manager_id' => ['required', 'integer', Rule::exists('users', 'id')->where(
                fn ($users) => $users->where('is_active', true)->orWhere('id', $this->authorizationModel()?->getAttribute('function_manager_id')),
            )],
            'customer_id' => ['nullable', 'integer', Rule::exists('registries', 'id')],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => [
                'required', 'integer',
                Rule::exists('company_sites', 'id')->where('company_id', $this->effective('company_id')),
            ],
            'operational_site_id' => ['required', 'integer', Rule::exists('operational_sites', 'id')],
            // Active functions only; the one already assigned stays valid even if deactivated since.
            'business_function_id' => ['required', 'integer', new ActiveBusinessFunction(array_map('intval', array_filter([$this->authorizationModel()?->getAttribute('business_function_id')])))],
            'notes' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'delivery_terms' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'procurement_plan' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'technical_requirements' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'special_conditions' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.supplier_id' => ['nullable', 'integer', Rule::exists('registries', 'id')->where('is_supplier', true)],
            'lines.*.reason' => ['nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'lines.*.unit_of_measure_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0', 'max:999999999999'],
            'lines.*.vat_rate_id' => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
        ];

        // Edit: a partial PATCH (spec 0256, D-1); a line, once sent, keeps its own rules.
        if (! $this->isUpdate()) {
            return $rules;
        }

        foreach ($rules as $key => $keyRules) {
            if (! str_starts_with($key, 'lines.')) {
                $rules[$key] = ['sometimes', ...$keyRules];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->enforceCompanySite($validator);
            $this->enforceApprover($validator);
        });
    }

    /** A partial edit that changes the company must pick a site of the new one (spec 0256, D-1). */
    private function enforceCompanySite(Validator $validator): void
    {
        if ($this->isUpdate() && $this->has('company_id') && ! $this->has('company_site_id')) {
            $validator->errors()->add('company_site_id', __('purchase_requests.company_site_required'));
        }
    }

    /**
     * The approver must be one of the resolver's options (spec 0245, D-1..D-3):
     * always on create, on update only when the requester, the approver or the
     * business function change, so legacy requests stay editable.
     */
    private function enforceApprover(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['requester_id', 'function_manager_id', 'business_function_id']) || ! $this->approverCheckNeeded()) {
            return;
        }

        $resolver = app(PurchaseRequestApproverResolver::class);
        $requester = User::query()->findOrFail((int) $this->effective('requester_id'));
        $function = BusinessFunction::query()->find((int) $this->effective('business_function_id'));

        if ($resolver->allows((int) $this->effective('function_manager_id'), $requester, $function)) {
            return;
        }

        $validator->errors()->add('function_manager_id', $resolver->resolve($requester, $function)['approvers']->isEmpty()
            ? __('purchase_requests.no_approver_available')
            : __('purchase_requests.approver_not_allowed'));
    }

    private function approverCheckNeeded(): bool
    {
        $stored = $this->authorizationModel();

        if (! $stored instanceof PurchaseRequest) {
            return true;
        }

        return (int) $this->effective('requester_id') !== (int) $stored->requester_id
            || (int) $this->effective('function_manager_id') !== (int) $stored->function_manager_id
            || (int) $this->effective('business_function_id') !== (int) $stored->business_function_id;
    }

    /** The submitted value of a header key, or the stored one when a partial edit leaves it out. */
    private function effective(string $key): mixed
    {
        return $this->has($key) ? $this->input($key) : $this->authorizationModel()?->getAttribute($key);
    }

    private function isUpdate(): bool
    {
        return $this->authorizationModel() instanceof PurchaseRequest;
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
