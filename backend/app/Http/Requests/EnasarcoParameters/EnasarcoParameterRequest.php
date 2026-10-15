<?php

namespace App\Http\Requests\EnasarcoParameters;

use App\DataObjects\EnasarcoParameters\EnasarcoParameterData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\EnasarcoParameter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload of POST /api/enasarco-parameters and of
 * PUT/PATCH /api/enasarco-parameters/{enasarcoParameter} (spec 0213, D-3):
 * POST and PUT take the full body; PATCH is partial (spec 0264, D-1): the
 * fields it omits keep their saved value, then the same rules run.
 * Authorization stays in the controller (Policy).
 */
class EnasarcoParameterRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int YEAR_MIN = 2000;

    private const int YEAR_MAX = 2100;

    private const int NOTES_MAX = 2000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Spec 0264, D-1: on PATCH the saved values sit UNDER the input (input wins).
     */
    protected function prepareForValidation(): void
    {
        $parameter = $this->parameter();

        if ($parameter === null || ! $this->isMethod('PATCH')) {
            return;
        }

        $stored = [
            'year' => $parameter->year,
            'total_rate' => $parameter->total_rate,
            'agent_share_percent' => $parameter->agent_share_percent,
            'single_firm_ceiling' => $parameter->single_firm_ceiling,
            'multi_firm_ceiling' => $parameter->multi_firm_ceiling,
            'notes' => $parameter->notes,
        ];

        $this->merge(array_diff_key($stored, $this->all()));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'year' => [
                'required', 'integer', 'between:'.self::YEAR_MIN.','.self::YEAR_MAX,
                Rule::unique('enasarco_parameters', 'year')->ignore($this->parameter()?->id),
            ],
            'total_rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'agent_share_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'single_firm_ceiling' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'multi_firm_ceiling' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->enforceFieldPermissions($validator));
    }

    public function toData(): EnasarcoParameterData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->safe()->all();

        return EnasarcoParameterData::fromValidated($validated);
    }

    protected function authorizationResource(): string
    {
        return 'enasarco-parameters';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->parameter();
    }

    private function parameter(): ?EnasarcoParameter
    {
        $parameter = $this->route('enasarcoParameter');

        return $parameter instanceof EnasarcoParameter ? $parameter : null;
    }
}
