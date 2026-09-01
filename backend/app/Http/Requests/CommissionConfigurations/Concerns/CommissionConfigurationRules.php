<?php

declare(strict_types=1);

namespace App\Http\Requests\CommissionConfigurations\Concerns;

use App\Authorization\AuthorizationRegistry;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\CommissionConfiguration;
use App\Models\User;
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
            // Spec 0090 D-4 (emends 0089 D-7): `recipient_type` is now an
            // ACCEPTED input, gated to the union of every role's allow-list
            // here (a coarse "is this a known recipient entity at all" gate);
            // the fine per-role check (AC-008) runs in `validateRecipient()`,
            // the only place with access to the (submitted or persisted)
            // role. Omitted, it stays derived from `recipient_role` (AC-009).
            'recipient_type' => ['nullable', 'string', Rule::in($this->allRecipientTypeAliases())],
            'recipient_id' => [
                'nullable', 'integer',
                // A submitted recipient_type only ever repaints an id
                // submitted ALONGSIDE it (spec 0090 R-3): without this, an
                // update could send `recipient_type` alone and silently
                // reinterpret the PERSISTED recipient_id against a
                // different table.
                Rule::requiredIf(fn () => $this->exists('recipient_type') && $this->input('recipient_type') !== null),
            ],
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
     * The union of every role's allow-list (spec 0090 D-4), derived from the
     * SAME single source `CommissionRecipientRole::allowedRecipientTypes()`
     * rather than a second hard-coded list — the duplication risk R-3 exists
     * to prevent.
     *
     * @return array<int, string>
     */
    private function allRecipientTypeAliases(): array
    {
        $aliases = [];

        foreach (CommissionRecipientRole::cases() as $role) {
            array_push($aliases, ...$role->allowedRecipientTypes());
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Cross-field recipient checks shared by store and update (spec 0089,
     * extended by 0090 D-4): RECIPIENT scope requires a recipient (AC-009 of
     * 0089), a submitted `recipient_type` must be in the (submitted or
     * persisted) role's allow-list (AC-008 of 0090), and a submitted
     * recipient_id must exist in the table the EFFECTIVE type derives to — a
     * SUPERVISOR id that only exists in `referents` is still invalid
     * (AC-010 of 0089) unless `recipient_type: referent` is explicitly chosen
     * (spec 0090 allows it for SUPERVISOR).
     */
    protected function validateRecipient(Validator $validator, ?CommissionConfiguration $model): void
    {
        $scope = $this->input('application_scope', $model?->application_scope->value);
        $recipientId = $this->exists('recipient_id') ? $this->input('recipient_id') : $model?->recipient_id;

        if ($scope === CommissionApplicationScope::Recipient->value && $recipientId === null) {
            $validator->errors()->add('recipient_id', __('validation.required', ['attribute' => 'recipient id']));

            return;
        }

        // `recipient_type` is the SAME logical datum as `recipient_id` split
        // across two columns (spec 0090 D-4) and is NOT its own field in
        // `CommissionConfigurationsAuthorization::fields()` (D-12, unchanged)
        // — so it inherits `recipient_id`'s field permission rather than
        // being its own ungoverned field. Without this, an actor with
        // `recipient_id` visible-but-not-editable could resubmit the SAME
        // numeric id under a DIFFERENT type and silently repoint who gets
        // paid (referent #7 -> user #7), passing `EnforcesFieldPermissions`'
        // value-diff check on `recipient_id` unchanged. Same 422 the actor
        // would get submitting a non-editable `recipient_id`.
        if ($this->exists('recipient_type') && ! $this->recipientIdFieldEditable($model)) {
            $validator->errors()->add('recipient_type', 'field not editable');

            return;
        }

        $roleValue = $this->input('recipient_role', $model?->recipient_role->value);
        $role = CommissionRecipientRole::tryFrom((string) $roleValue);

        if ($role === null) {
            // An invalid/missing role already fails its own rule; nothing
            // more to check here.
            return;
        }

        $type = $this->effectiveRecipientType($role, $model);

        if ($type !== null && ! in_array($type, $role->allowedRecipientTypes(), true)) {
            $validator->errors()->add('recipient_type', __('commission_configurations.recipient_type_not_allowed'));

            return;
        }

        if ($recipientId === null) {
            return;
        }

        /** @var class-string<Model>|null $recipientModel */
        $recipientModel = Relation::getMorphedModel($type ?? $role->recipientType());
        $exists = $recipientModel !== null && $recipientModel::query()->whereKey($recipientId)->exists();

        if (! $exists) {
            $validator->errors()->add('recipient_id', __('commission_configurations.invalid_recipient'));
        }
    }

    /**
     * Whether `recipient_id` is editable for the current actor + $model,
     * self-contained (no reach into the shared `EnforcesFieldPermissions`
     * trait, deliberately — that trait is used project-wide and a mistake in
     * it propagates everywhere): resolves the SAME `commission-configurations`
     * authorization the FormRequest's own `enforceFieldPermissions()` call
     * uses, read-only.
     */
    private function recipientIdFieldEditable(?CommissionConfiguration $model): bool
    {
        /** @var User $actor */
        $actor = $this->user();
        $baseAbility = $model === null ? 'create' : 'update';

        if (! $actor->can("commission-configurations.{$baseAbility}")) {
            // No base write ability: the base CRUD authorization (403 via
            // the Policy) is the relevant failure, not a field-level 422.
            return true;
        }

        $permission = app(AuthorizationRegistry::class)
            ->resolve('commission-configurations')
            ->fieldPermissions($actor, $model)['recipient_id'] ?? null;

        return $permission === null || $permission->editable;
    }

    /**
     * The recipient_type that will actually be persisted (spec 0090 D-4):
     * whatever is submitted; else, on an update that leaves `recipient_id`
     * untouched, the persisted value; else the (submitted or persisted)
     * role's own default. Mirrors `UpdateCommissionConfigurationData`'s
     * derivation so validation and persistence never diverge.
     */
    private function effectiveRecipientType(CommissionRecipientRole $role, ?CommissionConfiguration $model): ?string
    {
        if ($this->exists('recipient_type')) {
            return $this->input('recipient_type');
        }

        if ($model !== null && ! $this->exists('recipient_id')) {
            return $model->recipient_type;
        }

        return $role->recipientType();
    }
}
