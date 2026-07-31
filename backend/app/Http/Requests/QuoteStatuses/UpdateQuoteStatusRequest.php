<?php

namespace App\Http\Requests\QuoteStatuses;

use App\DataObjects\QuoteStatuses\UpdateQuoteStatusData;
use App\Enums\QuoteStatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\QuoteStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/quote-statuses/{quoteStatus}
 * (spec 0065): a plain clone of UpdateOpportunityStatusRequest. Every field
 * is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $quoteStatus)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model. `name` is unique ignoring self. `sort_order` is not
 * accepted here (see App\Services\Statuses\StatusOrderManager); `group`
 * (App\Enums\QuoteStatusGroup) — App\Services\Statuses\SystemStatusGuard rejects
 * it outright, at the Service layer, when the target row is a system
 * status.
 */
class UpdateQuoteStatusRequest extends FormRequest
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
        /** @var QuoteStatus $quoteStatus */
        $quoteStatus = $this->route('quoteStatus');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('quote_statuses', 'name')->ignore($quoteStatus->id)],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'group' => ['sometimes', 'string', Rule::enum(QuoteStatusGroup::class)],
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
        /** @var QuoteStatus $quoteStatus */
        $quoteStatus = $this->route('quoteStatus');

        return $quoteStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateQuoteStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateQuoteStatusData::fromValidated($validated);
    }
}
