<?php

declare(strict_types=1);

namespace App\Http\Requests\InvoiceTags;

use App\DataObjects\InvoiceTags\CreateInvoiceTagData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/invoice-tags (spec 0247, D-5). Authorization stays in the
 * controller; EnforcesFieldPermissions rejects fields the actor cannot edit.
 * `color` is checked against the BadgeTokens allow-list; `sort_order` is
 * server-managed and explicitly `prohibited`.
 */
class StoreInvoiceTagRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('invoice_tags', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['required', 'string', Rule::in(BadgeTokens::colors())],
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
        return null;
    }

    public function toData(): CreateInvoiceTagData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateInvoiceTagData::fromValidated($validated);
    }
}
