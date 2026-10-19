<?php

namespace App\Http\Requests\JobActivities;

use App\DataObjects\JobActivities\JobActivityData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\JobActivity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/job-activities/{jobActivity} (spec 0271).
 * Every field is `sometimes` to support partial updates.
 */
class UpdateJobActivityRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $jobActivity = $this->route('jobActivity');
        $ignoreId = $jobActivity instanceof JobActivity ? $jobActivity->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.StoreJobActivityRequest::NAME_MAX, Rule::unique('job_activities', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.StoreJobActivityRequest::DESCRIPTION_MAX],
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
        return 'job-activities';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var JobActivity $jobActivity */
        $jobActivity = $this->route('jobActivity');

        return $jobActivity;
    }

    public function toData(): JobActivityData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return JobActivityData::fromValidated($validated);
    }
}
