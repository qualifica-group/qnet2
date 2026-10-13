<?php

declare(strict_types=1);

namespace App\Http\Requests\InvoiceTags;

use App\DataObjects\InvoiceTags\UpdateInvoiceTagData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\InvoiceTag;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT/PATCH /api/invoice-tags/{invoiceTag} (spec 0247, D-5). Every
 * field is `sometimes` for partial updates; `name` is unique ignoring self and
 * `sort_order` is `prohibited`.
 */
class UpdateInvoiceTagRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $invoiceTag = $this->route('invoiceTag');
        $ignoreId = $invoiceTag instanceof InvoiceTag ? $invoiceTag->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('invoice_tags', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['sometimes', 'required', 'string', Rule::in(BadgeTokens::colors())],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'invoice-tags';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var InvoiceTag $invoiceTag */
        $invoiceTag = $this->route('invoiceTag');

        return $invoiceTag;
    }

    public function toData(): UpdateInvoiceTagData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateInvoiceTagData::fromValidated($validated);
    }
}
