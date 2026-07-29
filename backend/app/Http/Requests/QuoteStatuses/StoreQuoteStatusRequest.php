<?php

namespace App\Http\Requests\QuoteStatuses;

use App\DataObjects\QuoteStatuses\CreateQuoteStatusData;
use App\Enums\StatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/quote-statuses (spec 0065): a plain
 * clone of StoreOpportunityStatusRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', QuoteStatus::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). `name` is unique. `sort_order` is not
 * accepted here (absent from rules() -> validated() silently drops it,
 * "unknown field ignorato") — server-managed, see
 * App\Services\Statuses\StatusOrderManager. `group` (App\Enums\StatusGroup)
 * is REQUIRED — every row carries a classification.
 */
class StoreQuoteStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via QuoteStatusPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('quote_statuses', 'name')],
            'color' => ['nullable', 'string', 'max:32'],
            'group' => ['required', 'string', Rule::enum(StatusGroup::class)],
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
        return 'quote-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateQuoteStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateQuoteStatusData::fromValidated($validated);
    }
}
