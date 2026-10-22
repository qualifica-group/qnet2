<?php

namespace App\Http\Requests\AbsenceRequests;

use App\DataObjects\Absences\AbsenceRequestData;
use App\Enums\AbsenceDayPart;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\User;
use App\Services\Absences\Requests\AbsenceRequestVisibility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST and PATCH /api/absence-requests (spec 0278). POST also takes
 * `submit` and, for the HR office only, `user_id`. On PATCH a request the actor
 * cannot see is a 404 before any validation runs.
 */
class AbsenceRequestWriteRequest extends FormRequest
{
    public const int NOTE_MAX_LENGTH = 2000;

    public function authorize(): bool
    {
        /** @var User $actor */
        $actor = $this->user();

        if (! $this->isMethod('POST')) {
            app(AbsenceRequestVisibility::class)->assertCanView($actor, $this->route('absenceRequest'));

            return true;
        }

        $forSomeoneElse = $this->filled('user_id') && (int) $this->input('user_id') !== $actor->id;

        return $actor->can('create', AbsenceRequest::class)
            && (! $forSomeoneElse || app(AbsenceRequestVisibility::class)->canManageAll($actor));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'absence_type_id' => ['required', 'integer', Rule::exists(AbsenceType::class, 'id')->where('is_active', true)],
            'is_full_day' => ['required', 'boolean'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'day_part' => ['nullable', Rule::in(AbsenceDayPart::values()), Rule::prohibitedIf($this->boolean('is_full_day'))],
            'requested_minutes' => [Rule::requiredIf(! $this->boolean('is_full_day') && ! $this->filled('day_part')), 'nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:'.self::NOTE_MAX_LENGTH],
        ];

        if (! $this->boolean('is_full_day')) {
            $rules['ends_on'][] = 'same:starts_on';
        }

        if ($this->isMethod('POST')) {
            $rules['submit'] = ['sometimes', 'boolean'];
            $rules['user_id'] = ['sometimes', 'integer', Rule::exists(User::class, 'id')];
        }

        return $rules;
    }

    /** A mode the type does not allow is a 422 on `is_full_day` (spec 0304 D-9). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = AbsenceType::query()->find($this->input('absence_type_id'));

            if ($type !== null && ! $this->modeAllowedBy($type)) {
                $validator->errors()->add('is_full_day', __('absence_requests.mode_not_allowed'));
            }
        });
    }

    private function modeAllowedBy(AbsenceType $type): bool
    {
        return match (true) {
            $this->boolean('is_full_day') => $type->allows_full_day,
            $this->filled('day_part') => $type->allows_half_day,
            default => $type->allows_hourly,
        };
    }

    public function toData(): AbsenceRequestData
    {
        return AbsenceRequestData::fromValidated($this->validated());
    }

    /** The person the request is for: `user_id` (HR office) or the actor. */
    public function personId(): int
    {
        return (int) ($this->validated('user_id') ?? $this->user()->id);
    }

    public function shouldSubmit(): bool
    {
        return (bool) $this->validated('submit', false);
    }
}
