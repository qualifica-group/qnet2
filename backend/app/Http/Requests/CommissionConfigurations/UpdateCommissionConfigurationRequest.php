<?php

namespace App\Http\Requests\CommissionConfigurations;

use App\DataObjects\CommissionConfigurations\UpdateCommissionConfigurationData;
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

            $validFrom = (string) $this->input('valid_from', $model->valid_from->format('Y-m-d'));
            $validUntil = $this->exists('valid_until')
                ? $this->input('valid_until')
                : $model->valid_until?->format('Y-m-d');

            if ($validUntil !== null && $validUntil < $validFrom) {
                $validator->errors()->add('valid_until', __('validation.after_or_equal', ['date' => 'valid from']));
            }
        });
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
        return UpdateCommissionConfigurationData::fromValidated($this->validated());
    }
}
