<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT /api/time-entries/{timeEntry} (spec 0122,
 * data_contract: "stesso body di POST senza user_id"). A FULL replace, not a
 * partial PATCH — every field StoreTimeEntryRequest requires stays required
 * here too (AC-009: PUT with `user_id` in the body is 422).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via `authorize('update', $timeEntry)` — TimeEntryPolicy's
 * ownership rule, D-8).
 */
class UpdateTimeEntryRequest extends FormRequest
{
    private const int TITLE_MAX = 191;

    private const int NOTES_MAX = 5000;

    private const string TIME_FORMAT = 'H:i';

    private const string DATE_FORMAT = 'Y-m-d';

    public function authorize(): bool
    {
        // Authorization handled in the controller via TimeEntryPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'date' => ['required', 'date_format:'.self::DATE_FORMAT],
            'title' => ['required_without:task_id', 'nullable', 'string', 'max:'.self::TITLE_MAX],
            'task_type_id' => [
                'required',
                'integer',
                Rule::exists('task_types', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'start_time' => ['nullable', 'string', 'date_format:'.self::TIME_FORMAT, 'required_with:end_time'],
            'end_time' => ['nullable', 'string', 'date_format:'.self::TIME_FORMAT, 'required_with:start_time', 'after:start_time'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX],
            'registry_id' => ['sometimes', 'nullable', 'integer', Rule::exists('registries', 'id')],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', Rule::exists('opportunities', 'id')],
            'work_order_id' => ['sometimes', 'nullable', 'integer', Rule::exists('work_orders', 'id')],
            'task_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tasks', 'id')],
        ];
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): TimeEntryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TimeEntryData::fromValidated($validated);
    }
}
