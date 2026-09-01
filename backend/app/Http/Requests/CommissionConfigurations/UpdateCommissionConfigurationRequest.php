<?php

namespace App\Http\Requests\CommissionConfigurations;

use App\DataObjects\CommissionConfigurations\UpdateCommissionConfigurationData;
use App\Enums\CommissionRecipientRole;
use App\Http\Requests\CommissionConfigurations\Concerns\CommissionConfigurationRules;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\CommissionConfiguration;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionConfigurationRequest extends FormRequest
{
    use CommissionConfigurationRules;
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->configurationRules(true);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            /** @var CommissionConfiguration $model */
            $model = $this->route('commissionConfiguration');
            $scope = $this->input('application_scope', $model->application_scope->value);
            $categoryId = $this->exists('product_category_id') ? $this->input('product_category_id') : $model->product_category_id;
            $productId = $this->exists('product_id') ? $this->input('product_id') : $model->product_id;

            if ($scope === 'PRODUCT' && ($productId === null || $categoryId !== null)) {
                $validator->errors()->add('application_scope', __('commission_configurations.invalid_scope'));
            }
            if ($scope === 'PRODUCT_CATEGORY' && ($categoryId === null || $productId !== null)) {
                $validator->errors()->add('application_scope', __('commission_configurations.invalid_scope'));
            }
            if ($scope === 'RECIPIENT' && ($categoryId !== null || $productId !== null)) {
                $validator->errors()->add('application_scope', __('commission_configurations.invalid_scope'));
            }

            $validFrom = (string) $this->input('valid_from', $model->valid_from->format('Y-m-d'));
            $validUntil = $this->exists('valid_until')
                ? $this->input('valid_until')
                : $model->valid_until?->format('Y-m-d');

            if ($validUntil !== null && $validUntil < $validFrom) {
                $validator->errors()->add('valid_until', __('validation.after_or_equal', ['date' => 'valid from']));
            }

            $this->guardRecipientRoleChange($validator, $model);
            $this->validateRecipient($validator, $model);
        });
    }

    /**
     * Spec 0090 D-9 (emends 0089 D-9): changing `recipient_role` toward a
     * role whose allow-list no longer admits the persisted `recipient_type`,
     * without resubmitting `recipient_id`, would silently re-point that id
     * at a different table (e.g. a `referents` id reinterpreted as a
     * `users` id) or silently drop the recipient. Both are refused; the
     * caller must either resubmit a valid `recipient_id` for the new role or
     * clear it explicitly with `null` (AC-012, AC-013 of 0089). Since 0090
     * widens several roles' allow-lists, a role change that KEEPS the
     * persisted type admissible (e.g. COMMERCIAL -> SUPERVISOR keeping a
     * `referent`) is now lawful and must NOT trip this guard (AC-018).
     */
    private function guardRecipientRoleChange(Validator $validator, CommissionConfiguration $model): void
    {
        if (! $this->exists('recipient_role') || $model->recipient_type === null || $this->exists('recipient_id')) {
            return;
        }

        $newRole = CommissionRecipientRole::tryFrom((string) $this->input('recipient_role'));

        if ($newRole !== null && ! in_array($model->recipient_type, $newRole->allowedRecipientTypes(), true)) {
            $validator->errors()->add('recipient_id', __('commission_configurations.recipient_role_changed'));
        }
    }

    protected function authorizationResource(): string
    {
        return 'commission-configurations';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->route('commissionConfiguration');
    }

    public function toData(): UpdateCommissionConfigurationData
    {
        /** @var CommissionConfiguration $model */
        $model = $this->route('commissionConfiguration');

        return UpdateCommissionConfigurationData::fromValidated($this->validated(), $model);
    }
}
