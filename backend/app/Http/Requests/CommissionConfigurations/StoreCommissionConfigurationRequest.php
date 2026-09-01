<?php

namespace App\Http\Requests\CommissionConfigurations;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\Http\Requests\CommissionConfigurations\Concerns\CommissionConfigurationRules;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommissionConfigurationRequest extends FormRequest
{
    use CommissionConfigurationRules;
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->configurationRules(false);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateRecipient($validator, null);
        });
    }

    protected function authorizationResource(): string
    {
        return 'commission-configurations';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    public function toData(): CreateCommissionConfigurationData
    {
        return CreateCommissionConfigurationData::fromValidated($this->validated());
    }
}
