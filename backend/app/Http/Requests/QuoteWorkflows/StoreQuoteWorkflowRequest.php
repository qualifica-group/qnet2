<?php

declare(strict_types=1);

namespace App\Http\Requests\QuoteWorkflows;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\QuoteWorkflows\Concerns\ValidatesWorkflowCriteria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/quote-workflows (spec 0047, moved onto
 * the Offerta by spec 0083 D-6). Authorization is intentionally NOT handled
 * here (it stays in the controller via authorize('create', QuoteWorkflow::class)).
 * `criteria` is required (min:1, AC-008); `statuses` is optional and covers
 * ONLY the intermediate custom rows — the system rows (open/closed_won/
 * closed_lost) are created automatically by the Service.
 */
class StoreQuoteWorkflowRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesWorkflowCriteria;

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
            'name' => ['required', 'string', 'max:191', Rule::unique('quote_workflows', 'name')],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->criteriaRules(required: true),
            ...$this->statusesRules(allowIds: false),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateCriteria($validator, excludeWorkflowId: null);
        });
    }

    protected function authorizationResource(): string
    {
        return 'quote-workflows';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateQuoteWorkflowData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateQuoteWorkflowData::fromValidated($validated);
    }
}
