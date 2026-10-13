<?php

namespace App\Http\Requests\AgentFiscalProfiles;

use App\DataObjects\AgentFiscalProfiles\AgentFiscalProfileData;
use App\Enums\EnasarcoMandateType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\AgentFiscalProfile;
use App\Rules\NonOverlappingFiscalProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload of POST /api/agent-fiscal-profiles and of
 * PUT/PATCH /api/agent-fiscal-profiles/{agentFiscalProfile} (spec 0212, D-8):
 * both take the same full body. Rate/base are required only with the
 * withholding switch on, the mandate type only with ENASARCO on, and both are
 * refused otherwise. Authorization stays in the controller (Policy).
 */
class AgentFiscalProfileRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NOTES_MAX = 2000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $withholding = $this->boolean('withholding_enabled');
        $enasarco = $this->boolean('enasarco_enabled');

        return [
            'referent_id' => ['required', 'integer', Rule::exists('referents', 'id')],
            'withholding_enabled' => ['required', 'boolean'],
            'withholding_rate' => $this->percentRules($withholding),
            'withholding_base_percent' => $this->percentRules($withholding),
            'enasarco_enabled' => ['required', 'boolean'],
            'enasarco_mandate_type' => [
                'nullable', Rule::enum(EnasarcoMandateType::class),
                Rule::requiredIf($enasarco), Rule::prohibitedIf(! $enasarco),
            ],
            'vat_rate_id' => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
            'valid_from' => [
                'required', 'date',
                new NonOverlappingFiscalProfile($this->input('referent_id'), $this->input('valid_until'), $this->profile()?->id),
            ],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->enforceFieldPermissions($validator));
    }

    public function toData(): AgentFiscalProfileData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->safe()->all();

        return AgentFiscalProfileData::fromValidated($validated);
    }

    protected function authorizationResource(): string
    {
        return 'agent-fiscal-profiles';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->profile();
    }

    /**
     * A 0..100 percentage with at most two decimals, required while the
     * owning switch is on and refused while it is off.
     *
     * @return array<int, mixed>
     */
    private function percentRules(bool $switchedOn): array
    {
        return [
            'nullable', 'numeric', 'between:0,100', 'decimal:0,2',
            Rule::requiredIf($switchedOn), Rule::prohibitedIf(! $switchedOn),
        ];
    }

    private function profile(): ?AgentFiscalProfile
    {
        $profile = $this->route('agentFiscalProfile');

        return $profile instanceof AgentFiscalProfile ? $profile : null;
    }
}
