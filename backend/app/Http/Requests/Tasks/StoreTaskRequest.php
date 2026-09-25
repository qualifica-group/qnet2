<?php

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\CreateTaskData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/tasks (spec 0101).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Task::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 *
 * Four fields are `prohibited` UNCONDITIONALLY, regardless of value and
 * regardless of the actor's role (AC-011/AC-035/AC-009): `creator_id`, the
 * creator is server-side (D-10); `completion_percentage`, derived from the
 * status (D-6); `is_blocked`, written ONLY by the block/unblock domain
 * actions, never at creation time (spec 0116 D-6); and since spec 0127 D-1
 * `completion_date`: only the completion actions (TaskCompletionService) —
 * or, since spec 0154 D-6, `App\Services\Tasks\TaskCreationCompletion` on
 * `is_completed: true` — write it, so a negative close never carries one.
 * Field permissions alone cannot express this — the privileged role bypasses
 * every ceiling — so the rule lives here, ahead of and independent from
 * that mechanism.
 *
 * `task_status_id` is no longer unconditionally prohibited (spec 0154 D-10,
 * REQUIREMENT CHANGED from spec 0118 D-3): a `sometimes` manual override is
 * now admitted on create, forbidden outright when `is_completed: true` (the
 * two are mutually exclusive destinations for the same column). The value
 * itself is not re-validated here beyond existence — whether it is a phase a
 * POST may manually choose, and whether `open`/`assigned` re-derive rather
 * than apply as chosen, is `App\Services\Tasks\TaskInitialStatusResolver::resolveForCreate()`'s
 * resulting-state job (D-10), the same split every other record-link
 * coherence rule in this module draws.
 *
 * `start_time`/`end_time` are `H:i` TEXT (D-11): `FieldDefinition` has no
 * `time` type, and adding one to the shared catalogue is out of scope.
 *
 * Three rules are deliberately NOT here, because each is a function of the
 * RESULTING record rather than of the payload: the referente/anagrafica
 * coherence (AC-014), the sub-task hierarchy (D-12) and the closing feedback
 * (D-7). All three are enforced by App\Services\TaskService inside the write
 * transaction.
 *
 * `subtasks` (spec 0155, D-3; spec 0161, D-1 REQUIREMENT CHANGED "one level"
 * to `subtasks.*.subtasks.*.subtasks.*`, three levels below the task —
 * figlio/nipote/pronipote): each row's own shape at every depth is
 * deliberately SMALLER than the parent's — no `registry_id`/`referent_id`/
 * `opportunity_id`/`work_order_id`/`work_order_stage_id`/`lead_id`/
 * `is_private`/`requester_id`/`recurrence` key exists on a row at all, since
 * App\Services\Tasks\TaskSubtaskBatchCreator always copies the first six off
 * the row's own DIRECT parent, always nulls the seventh, and always derives
 * the requester/status the same way a plain create does.
 * `assignee_ids`/`watcher_ids`/`task_type_id`/`task_priority_id`/
 * `task_importance_id`/`end_date` are `sometimes` on purpose: OMITTED means
 * "inherit the direct parent's own resulting value" (the batch creator's
 * job, spec 0161 D-2), never "clear it" — a row that submits the key, even
 * empty, means exactly that value. Laravel's own `subtasks.*` wildcard
 * naming already produces the `subtasks.N.field`/`subtasks.N.subtasks.M.field`
 * error key the contract requires, with no remapping needed at this layer.
 * A FOURTH level (`subtasks.*.subtasks.*.subtasks.*.subtasks`) is
 * `prohibited` outright (AC-002): 422 on that nested key, never silently
 * dropped. The TOTAL node count across the whole tree — not any one level's
 * own array size — is capped at `MAX_SUBTASK_NODES` in withValidator() below
 * (AC-003): Laravel's per-field `max` rule has no way to sum across nested
 * wildcard paths on its own.
 *
 * `recurrence` (spec 0120, data_contract) is `sometimes|nullable|array`, with
 * every inner field conditioned on `frequency`/`ends` via
 * `required_if`/`prohibited_unless` pairs — the same pattern used nowhere
 * else in this file because no other field on the catalogue has an internal
 * shape of its own. `task_recurrence_id` is `prohibited` alongside the other
 * server-owned columns: no payload ever names the row directly, only the
 * `recurrence` object App\Services\Tasks\TaskRecurrenceService turns into
 * one. AC-027's "recurrence without an end_date" is a SEPARATE check, added
 * in withValidator() below: `end_date` is already unconditionally required
 * on POST, so this never independently blocks a request — it exists only to
 * put `recurrence` itself in the error bag, which is what the criterion
 * asserts on.
 */
class StoreTaskRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int TITLE_MAX = 191;

    private const string TIME_FORMAT = 'H:i';

    /** Spec 0161, D-1: figlio/nipote/pronipote — 3 `subtasks` arrays deep. */
    private const int MAX_SUBTASK_DEPTH = 3;

    /** Spec 0161, D-1: total nodes across the WHOLE tree, every level summed. */
    private const int MAX_SUBTASK_NODES = 50;

    public function authorize(): bool
    {
        // Authorization handled in the controller via TaskPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'creator_id' => ['prohibited'],
            'completion_percentage' => ['prohibited'],
            'is_blocked' => ['prohibited'],
            'task_recurrence_id' => ['prohibited'],
            'completion_date' => ['prohibited'],
            // D-10 of spec 0154: admitted, but never alongside `is_completed`
            // (the resulting status is then TaskCreationCompletion's own,
            // never the client's).
            'task_status_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_statuses', 'id'), 'prohibited_if:is_completed,true'],
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'description' => ['sometimes', 'nullable', 'string'],
            'registry_id' => ['sometimes', 'nullable', 'integer', Rule::exists('registries', 'id')],
            'referent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'parent_task_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tasks', 'id')],
            'task_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_types', 'id')],
            'task_priority_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_priorities', 'id')],
            'task_importance_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_importances', 'id')],
            'task_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_categories', 'id')],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', Rule::exists('opportunities', 'id')],
            'work_order_id' => ['sometimes', 'nullable', 'integer', Rule::exists('work_orders', 'id')],
            // Spec 0146, D-3: shape only (the row exists at all) — whether it
            // belongs to `work_order_id` and isn't on a sub-task is a
            // resulting-state question App\Services\Tasks\TaskStageGuard
            // answers inside the write transaction (422/409), the same split
            // the referent/registry coherence rule already draws.
            'work_order_stage_id' => ['sometimes', 'nullable', 'integer', Rule::exists('work_order_stages', 'id')],
            'requester_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['required', 'date'],
            'start_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'end_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'requires_closure_feedback' => ['sometimes', 'boolean'],
            'requires_validation' => ['sometimes', 'boolean'],
            'closure_feedback' => ['sometimes', 'nullable', 'string'],
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')],
            'watcher_ids' => ['sometimes', 'array'],
            'watcher_ids.*' => ['integer', Rule::exists('users', 'id')],
            // Spec 0154: D-2/D-4/D-6/D-7 fields.
            'is_private' => ['sometimes', 'boolean'],
            'lead_id' => ['sometimes', 'nullable', 'integer', Rule::exists('leads', 'id')],
            'is_completed' => ['sometimes', 'boolean'],
            'notify_assigned_users' => ['sometimes', 'boolean'],
            ...TaskRecurrenceRules::rules(),
            ...$this->subtaskRules(),
        ];
    }

    /**
     * One rule set per depth (1..MAX_SUBTASK_DEPTH), each identical bar its
     * own `subtasks.*` prefix — `subtasks`, then `subtasks.*.subtasks`, then
     * `subtasks.*.subtasks.*.subtasks` (spec 0161, D-1). A 4th prefix is
     * `prohibited` outright, so a client attempting a 4th level gets a 422 on
     * that exact nested key (AC-002) instead of it being silently dropped.
     *
     * @return array<string, array<int, mixed>>
     */
    private function subtaskRules(): array
    {
        $rules = [];
        $prefix = 'subtasks';

        for ($depth = 1; $depth <= self::MAX_SUBTASK_DEPTH; $depth++) {
            $rules += $this->subtaskRowRules($prefix);
            $prefix .= '.*.subtasks';
        }

        $rules[$prefix] = ['prohibited'];

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function subtaskRowRules(string $prefix): array
    {
        return [
            $prefix => ['sometimes', 'array', 'max:'.self::MAX_SUBTASK_NODES],
            "{$prefix}.*.title" => ['required', 'string', 'max:'.self::TITLE_MAX],
            "{$prefix}.*.description" => ['sometimes', 'nullable', 'string'],
            "{$prefix}.*.start_date" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.*.end_date" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.*.estimated_minutes" => ['sometimes', 'nullable', 'integer', 'min:0'],
            "{$prefix}.*.assignee_ids" => ['sometimes', 'array'],
            "{$prefix}.*.assignee_ids.*" => ['integer', Rule::exists('users', 'id')],
            "{$prefix}.*.watcher_ids" => ['sometimes', 'array'],
            "{$prefix}.*.watcher_ids.*" => ['integer', Rule::exists('users', 'id')],
            "{$prefix}.*.task_type_id" => ['sometimes', 'nullable', 'integer', Rule::exists('task_types', 'id')],
            "{$prefix}.*.task_priority_id" => ['sometimes', 'nullable', 'integer', Rule::exists('task_priorities', 'id')],
            "{$prefix}.*.task_importance_id" => ['sometimes', 'nullable', 'integer', Rule::exists('task_importances', 'id')],
            "{$prefix}.*.task_category_id" => ['sometimes', 'nullable', 'integer', Rule::exists('task_categories', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            // AC-027: a recurrence needs an end_date to anchor its
            // occurrences (D-5). `end_date` is already `required` above, so
            // this fires alongside it, not instead of it — the criterion
            // only asserts `recurrence` is in the error bag too.
            if ($this->filled('recurrence') && ! $this->filled('end_date')) {
                $validator->errors()->add('recurrence', 'A recurrence requires an end date.');
            }

            // AC-003 (spec 0161): the TOTAL node count across the whole
            // tree — every level summed, not any one array's own size.
            $totalSubtaskNodes = $this->countSubtaskNodes((array) $this->input('subtasks', []));

            if ($totalSubtaskNodes > self::MAX_SUBTASK_NODES) {
                $validator->errors()->add(
                    'subtasks',
                    'No more than '.self::MAX_SUBTASK_NODES.' sub-tasks are allowed across the whole tree.',
                );
            }
        });
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function countSubtaskNodes(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $count++;
            $count += $this->countSubtaskNodes(is_array($row['subtasks'] ?? null) ? $row['subtasks'] : []);
        }

        return $count;
    }

    protected function authorizationResource(): string
    {
        return 'tasks';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateTaskData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateTaskData::fromValidated($validated);
    }
}
