<?php

declare(strict_types=1);

namespace App\Http\Requests\QuoteWorkflows;

use App\DataObjects\QuoteWorkflows\UpdateQuoteWorkflowData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\QuoteWorkflows\Concerns\ValidatesWorkflowCriteria;
use App\Models\QuoteWorkflow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/quote-workflows/{quoteWorkflow}
 * (spec 0047, moved onto the Offerta by spec 0083 D-6). Every field is
 * `sometimes` (partial PATCH). Authorization is intentionally NOT handled
 * here (it stays in the controller via authorize('update', $quoteWorkflow)).
 * `criteria`, when submitted, is an authoritative full-replace sync (min:1 —
 * a workflow can never be left with zero criteria); `statuses`, when
 * submitted, syncs only the CUSTOM rows (`statuses.*.id` optional: present =
 * update, absent = new) — the system rows accept every descriptive field but
 * never a `group` change, enforced at the Service/WorkflowStatusWriter
 * layer.
 */
class UpdateQuoteWorkflowRequest extends FormRequest
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
        /** @var QuoteWorkflow $quoteWorkflow */
        $quoteWorkflow = $this->route('quoteWorkflow');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('quote_workflows', 'name')->ignore($quoteWorkflow->id)],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->criteriaRules(required: false),
            ...$this->statusesRules(allowIds: true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            /** @var QuoteWorkflow $quoteWorkflow */
            $quoteWorkflow = $this->route('quoteWorkflow');

            $this->validateCriteria($validator, excludeWorkflowId: $quoteWorkflow->id);
        });
    }

    protected function authorizationResource(): string
    {
        return 'quote-workflows';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var QuoteWorkflow $quoteWorkflow */
        $quoteWorkflow = $this->route('quoteWorkflow');

        return $quoteWorkflow;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateQuoteWorkflowData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateQuoteWorkflowData::fromValidated($validated);
    }
}
