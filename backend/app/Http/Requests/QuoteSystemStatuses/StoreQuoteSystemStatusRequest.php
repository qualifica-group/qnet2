<?php

declare(strict_types=1);

namespace App\Http\Requests\QuoteSystemStatuses;

use App\DataObjects\QuoteSystemStatuses\QuoteSystemStatusData;
use App\Enums\WorkflowStatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/quote-system-statuses (spec 0404 part A). Authorization in the
 * controller via QuoteSystemStatusPolicy.
 */
class StoreQuoteSystemStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

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
            'name' => ['required', 'string', 'max:100', Rule::unique('quote_system_statuses', 'name')],
            'color' => ['required', 'string', 'max:32'],
            'behavior' => ['required', 'string', Rule::enum(WorkflowStatusGroup::class)],
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
        return 'quote-system-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): QuoteSystemStatusData
    {
        return QuoteSystemStatusData::fromValidated($this->validated());
    }
}
