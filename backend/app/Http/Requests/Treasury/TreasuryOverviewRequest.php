<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /api/treasury-overview (spec 0362): `treasury-movements.view` (D-1). */
class TreasuryOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treasury-movements.view') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')]];
    }

    public function companyId(): ?int
    {
        return isset($this->validated()['company_id']) ? (int) $this->validated()['company_id'] : null;
    }
}
