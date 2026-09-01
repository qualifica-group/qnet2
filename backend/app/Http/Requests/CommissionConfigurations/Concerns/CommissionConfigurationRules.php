<?php

declare(strict_types=1);

namespace App\Http\Requests\CommissionConfigurations\Concerns;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\CommissionConfiguration;
use App\Rules\SelectableProductCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @phpstan-require-extends FormRequest
 */
trait CommissionConfigurationRules
{
    /** @return array<string, array<int, mixed>> */
    protected function configurationRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$required, 'string', 'max:191'],
            'recipient_role' => [...$required, Rule::enum(CommissionRecipientRole::class)],
            'application_scope' => [...$required, Rule::enum(CommissionApplicationScope::class)],
            'product_category_id' => [
                // Spec 0074: only a selectable category can carry a commission
                // rule — the resolver matches it EXACTLY (no subtree walk), so
                // a rule on a container category could never fire anyway.
                'nullable', 'integer', new SelectableProductCategory($this->exemptProductCategoryIds()),
                Rule::requiredIf(fn () => $this->input('application_scope') === CommissionApplicationScope::ProductCategory->value),
                Rule::prohibitedIf(fn () => $this->scopeExcludesCategory()),
            ],
            'product_id' => [
                'nullable', 'integer', Rule::exists('products', 'id'),
                Rule::requiredIf(fn () => $this->input('application_scope') === CommissionApplicationScope::Product->value),
                Rule::prohibitedIf(fn () => $this->scopeExcludesProduct()),
            ],
            // `recipient_id` is the only recipient field accepted from the
            // client: `recipient_type` is derived server-side from
            // `recipient_role` (spec 0089 D-7) and is never a form input, so
            // it carries no validation rule — any value submitted for it is
            // simply ignored by `$request->validated()` (AC-011).
            'recipient_id' => ['nullable', 'integer'],
            'commission_type' => [...$required, Rule::enum(CommissionType::class)],
            'value' => [...$required, 'numeric', 'min:0', 'decimal:0,4', 'max:99999999999.9999'],
            'priority' => [...$required, 'integer', 'min:0'],
            'valid_from' => [...$required, 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'status' => [...$required, Rule::enum(CommissionConfigurationStatus::class)],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The category already persisted on the configuration being updated,
     * exempt from the selectable check (spec 0074 D-3b). Empty on create.
     *
     * @return array<int, int>
     */
    protected function exemptProductCategoryIds(): array
    {
        $configuration = $this->route('commissionConfiguration');

        return $configuration instanceof CommissionConfiguration && $configuration->product_category_id !== null
            ? [(int) $configuration->product_category_id]
            : [];
    }

    /** Spec 0089 D-2: RECIPIENT scope admits neither product nor category. */
    private function scopeExcludesCategory(): bool
    {
        return in_array($this->input('application_scope'), [
            CommissionApplicationScope::Product->value,
            CommissionApplicationScope::Recipient->value,
        ], true);
    }

    private function scopeExcludesProduct(): bool
    {
        return in_array($this->input('application_scope'), [
            CommissionApplicationScope::ProductCategory->value,
            CommissionApplicationScope::Recipient->value,
        ], true);
    }

    /**
     * Cross-field recipient checks shared by store and update (spec 0089):
     * RECIPIENT scope requires a recipient (AC-009), and a submitted
     * recipient_id must exist in the table its (submitted or persisted) role
     * derives to — a SUPERVISOR id that only exists in `referents` is still
     * invalid (AC-010).
     */
    protected function validateRecipient(Validator $validator, ?CommissionConfiguration $model): void
    {
        $scope = $this->input('application_scope', $model?->application_scope->value);
        $recipientId = $this->exists('recipient_id') ? $this->input('recipient_id') : $model?->recipient_id;

        if ($scope === CommissionApplicationScope::Recipient->value && $recipientId === null) {
            $validator->errors()->add('recipient_id', __('validation.required', ['attribute' => 'recipient id']));

            return;
        }

        if ($recipientId === null) {
            return;
        }

        $roleValue = $this->input('recipient_role', $model?->recipient_role->value);
        $role = CommissionRecipientRole::tryFrom((string) $roleValue);

        if ($role === null) {
            // An invalid/missing role already fails its own rule; nothing
            // more to check here.
            return;
        }

        /** @var class-string<Model>|null $recipientModel */
        $recipientModel = Relation::getMorphedModel($role->recipientType());
        $exists = $recipientModel !== null && $recipientModel::query()->whereKey($recipientId)->exists();

        if (! $exists) {
            $validator->errors()->add('recipient_id', __('commission_configurations.invalid_recipient'));
        }
    }
}
