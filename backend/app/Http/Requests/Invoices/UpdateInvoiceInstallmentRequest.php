<?php

namespace App\Http\Requests\Invoices;

use App\Authorization\AuthorizationRegistry;
use App\DataObjects\Invoices\UpdateInvoiceInstallmentData;
use App\Models\InvoiceInstallment;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /invoice-installments/{installment} (spec 0197, D-6): due date and
 * payment method code. Authorization lives here: the `update` ability plus the
 * field permission of every submitted field (a locked field is a 403).
 */
class UpdateInvoiceInstallmentRequest extends FormRequest
{
    private const int PAYMENT_METHOD_CODE_MAX = 32;

    /** The only fields this endpoint accepts. */
    private const array FIELDS = ['due_date', 'payment_method_code'];

    public function authorize(): bool
    {
        /** @var User $actor */
        $actor = $this->user();
        $installment = $this->installment();

        if (! $actor->can('update', $installment)) {
            return false;
        }

        $permissions = app(AuthorizationRegistry::class)->resolve('invoice-installments')->fieldPermissions($actor, $installment);

        foreach (self::FIELDS as $field) {
            if ($this->has($field) && ! $permissions[$field]->editable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $documentDate = $this->installment()->loadMissing('invoice:id,document_date')->invoice->document_date->toDateString();

        return [
            'due_date' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:'.$documentDate],
            'payment_method_code' => ['sometimes', 'nullable', 'string', 'max:'.self::PAYMENT_METHOD_CODE_MAX, Rule::exists('payment_methods', 'payment_method_code')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(self::FIELDS)) {
                $validator->errors()->add('due_date', 'At least one field is required.');
            }
        });
    }

    public function toData(): UpdateInvoiceInstallmentData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateInvoiceInstallmentData::fromValidated($validated);
    }

    private function installment(): InvoiceInstallment
    {
        /** @var InvoiceInstallment $installment */
        $installment = $this->route('installment');

        return $installment;
    }
}
