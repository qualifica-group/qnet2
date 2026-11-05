<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /api/account-statements/balances (spec 0360): `treasury-movements.view`. */
class BalancesRequest extends FormRequest
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
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function companyId(): ?int
    {
        return isset($this->validated()['company_id']) ? (int) $this->validated()['company_id'] : null;
    }

    public function balanceDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated()['date'] ?? 'today');
    }
}
