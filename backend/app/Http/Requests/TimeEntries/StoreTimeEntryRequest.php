<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/time-entries (spec 0122,
 * data_contract). Authorization is intentionally NOT handled here (it stays
 * in the controller via TimeEntryPolicy/`time-entries.*` — see
 * TimeEntryController::store).
 *
 * `start_time`/`end_time` mirror StoreTaskRequest's own `H:i` TEXT fields
 * (D-11 of spec 0101): both or neither (`required_with` each other,
 * AC-002), `end_time` strictly AFTER `start_time` — no midnight crossing
 * (D-6). Neither carries `sometimes`: `required_with` must fire even when
 * the OTHER field is the only one present in the payload, and `sometimes`
 * would skip that check entirely on a key absent from the request (see the
 * DTO's own note on why this differs from the other optional fields below).
 *
 * `title`/`registry_id`/`opportunity_id`/`work_order_id`/`task_id` are
 * validated at face value — shape only. The D-5 semantics (task overrides
 * every one of them; opportunity+commessa exclusivity; client coherence)
 * are NOT expressible as a FormRequest rule, since they depend on the
 * RESOLVED record, not the raw payload: `TimeEntryLinkResolver` enforces
 * them inside the Service, same split as StoreTaskRequest/TaskService's own
 * referent-registry coherence rule.
 */
class StoreTimeEntryRequest extends FormRequest
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
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
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
