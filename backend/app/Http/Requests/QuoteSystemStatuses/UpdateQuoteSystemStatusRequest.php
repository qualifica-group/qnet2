<?php

declare(strict_types=1);

namespace App\Http\Requests\QuoteSystemStatuses;

use App\DataObjects\QuoteSystemStatuses\QuoteSystemStatusData;
use App\Enums\WorkflowStatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\QuoteSystemStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT/PATCH /api/quote-system-statuses/{quoteSystemStatus} (spec 0404 part
 * A). The system-row rule (behavior fixed, A-D3) lives in the service.
 */
class UpdateQuoteSystemStatusRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('quote_system_statuses', 'name')->ignore($this->status()->id)],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
            'behavior' => ['sometimes', 'required', 'string', Rule::enum(WorkflowStatusGroup::class)],
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
        return $this->status();
    }

    public function toData(): QuoteSystemStatusData
    {
        return QuoteSystemStatusData::fromValidated($this->validated());
    }

    private function status(): QuoteSystemStatus
    {
        /** @var QuoteSystemStatus $status */
        $status = $this->route('quoteSystemStatus');

        return $status;
    }
}
