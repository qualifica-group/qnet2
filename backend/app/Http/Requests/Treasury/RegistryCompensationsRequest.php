<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use App\Http\Requests\Concerns\DeniesWithMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /registries/{registry}/compensation-candidates (company_id required) and
 * GET /registries/{registry}/compensations (company_id optional), spec 0364:
 * `ledger.view` like the ledger. Authorization runs before the validation.
 */
class RegistryCompensationsRequest extends FormRequest
{
    use DeniesWithMessage;

    public function authorize(): bool
    {
        return $this->user()?->can('ledger.view') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->routeIs('registries.compensation-candidates');

        return ['company_id' => [$required ? 'required' : 'nullable', 'integer', Rule::exists('companies', 'id')]];
    }

    public function companyId(): ?int
    {
        $value = $this->validated('company_id');

        return $value === null ? null : (int) $value;
    }

    protected function deniedMessage(): string
    {
        return __('treasury_compensations.view_forbidden');
    }
}
