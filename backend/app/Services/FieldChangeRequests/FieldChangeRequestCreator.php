<?php

declare(strict_types=1);

namespace App\Services\FieldChangeRequests;

use App\DataObjects\FieldChangeRequests\CreateFieldChangeRequestData;
use App\Enums\FieldChangeRequestStatus;
use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Notifications\FieldChangeRequestedNotification;
use App\Services\Table\CellValueValidator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Creates a `pending` field-change-request (spec 0078). The guard order
 * below is deliberate — it is what pins down which HTTP status a given
 * refusal reports (422 vs 403 vs 404), never mixed:
 *
 *   1. the field must be a REGISTERED protected field (422, AC-017)
 *   2. the record must exist in the domain's own scope (404, AC-022) and be
 *      visible to the actor (403)
 *   3. an actor who can ALREADY write the field directly is refused — they
 *      must edit it, not propose a change (422, AC-016)
 *   4. the requested value must validate against the column's own type
 *      (422, AC-018)
 *   5. the requested value must differ from the current one (422, AC-019)
 *   6. no OTHER request may already be `pending` for the same (record,
 *      field) pair (422, AC-020/D-4) — the `pending_key` UNIQUE index (D-5)
 *      is the portable last line of defence against a race, caught below.
 */
final class FieldChangeRequestCreator
{
    public function __construct(
        private readonly ProtectedFieldRegistry $registry,
        private readonly FieldChangeRequestValueResolver $resolver,
        private readonly CellValueValidator $valueValidator,
    ) {}

    /**
     * The notification is dispatched as plain sequential code AFTER
     * DB::transaction() returns, not via DB::afterCommit(): this method is
     * always invoked directly from a thin controller action with no OUTER
     * transaction of its own, so the two are behaviourally identical in
     * production, and the plain form stays observable under
     * `RefreshDatabase` in tests (afterCommit callbacks registered inside a
     * test's own wrapping transaction never fire, since that transaction is
     * rolled back rather than committed at the end of every test).
     */
    public function handle(CreateFieldChangeRequestData $data, User $actor): FieldChangeRequest
    {
        $fieldChangeRequest = DB::transaction(fn (): FieldChangeRequest => $this->create($data, $actor));

        $this->notifyViewers($fieldChangeRequest, $actor);

        return $fieldChangeRequest;
    }

    private function create(CreateFieldChangeRequestData $data, User $actor): FieldChangeRequest
    {
        // Step 1
        $protectedField = $this->registry->find($data->resource, $data->field);
        abort_if($protectedField === null, 422, __('This field is not open to change requests.'));

        // Step 2
        $definition = $this->resolver->definitionFor($data->resource);
        abort_unless($actor->can("{$data->resource}.view"), 403);
        $record = $this->resolver->record($definition, $data->subjectId);

        // Step 3
        abort_if($actor->can($protectedField->permission()), 422, __('You can edit this field directly.'));

        $columnConfig = $this->resolver->columnConfig($definition, $protectedField);
        abort_if($columnConfig === null, 422, __('This field is not open to change requests.'));

        // Step 4
        $requestedValue = $this->valueValidator->validate($columnConfig, $data->requestedValue);

        // Step 5
        $currentValue = $this->resolver->currentValue($record, $protectedField);
        abort_if($this->resolver->sameValue($currentValue, $requestedValue), 422, __('The requested value is the same as the current one.'));

        $pendingKey = sprintf('%s:%s:%s', $record->getMorphClass(), $record->getKey(), $data->field);

        // Step 6 (fast, readable pre-check; the UNIQUE index below is the
        // portable guarantee against a concurrent duplicate, D-5).
        abort_if(FieldChangeRequest::query()->where('pending_key', $pendingKey)->exists(), 422, __('A change request is already pending for this field.'));

        $subjectLabel = $this->resolver->subjectLabel($definition, $record, $actor);
        $currentLabel = $this->resolver->labelFor($columnConfig, $currentValue, $actor);
        $requestedLabel = $this->resolver->labelFor($columnConfig, $requestedValue, $actor);

        $fieldChangeRequest = new FieldChangeRequest([
            'resource' => $data->resource,
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
            'field' => $data->field,
            'current_value' => $currentValue,
            'requested_value' => $requestedValue,
            'current_label' => $currentLabel,
            'requested_label' => $requestedLabel,
            'reason' => $data->reason,
            'requested_by_id' => $actor->id,
        ]);
        // D-6: server-governed, never mass-assigned (see model docblock).
        // Explicit, not left to the DB column DEFAULT: a freshly `new`-ed
        // model never re-reads a DEFAULT applied at INSERT time, so leaving
        // `status` unset here would keep it null in memory after save().
        $fieldChangeRequest->status = FieldChangeRequestStatus::Pending;
        $fieldChangeRequest->pending_key = $pendingKey;

        try {
            $fieldChangeRequest->save();
        } catch (QueryException $exception) {
            abort_if($this->isUniqueViolation($exception), 422, __('A change request is already pending for this field.'));

            throw $exception;
        }

        activity($fieldChangeRequest->getTable())
            ->performedOn($fieldChangeRequest)
            ->causedBy($actor)
            ->event('field_change_request.created')
            ->withProperties([
                'resource' => $data->resource,
                'field' => $data->field,
                'subject_label' => $subjectLabel,
                'requested_value' => $requestedValue,
            ])
            ->log('Field change request created');

        return $fieldChangeRequest;
    }

    /**
     * Notifies every titolare of `field-change-requests.viewAny` (AC-024),
     * excluded the requester themselves — dispatched AFTER the creating
     * transaction commits (DB::transaction() above already returned) so a
     * slow/unavailable channel never holds the request open, mirroring
     * NoteService::syncMentionsAndNotify.
     */
    private function notifyViewers(FieldChangeRequest $fieldChangeRequest, User $actor): void
    {
        $recipients = User::permission('field-change-requests.viewAny')->get()
            ->reject(fn (User $user): bool => $user->id === $actor->id);

        if ($recipients->isEmpty()) {
            return;
        }

        $protectedField = $this->registry->find($fieldChangeRequest->resource, $fieldChangeRequest->field);
        $fieldLabel = $protectedField?->fieldLabel ?? $fieldChangeRequest->field;
        $subjectLabel = $this->resolver->subjectLabelOrFallback($fieldChangeRequest->resource, (int) $fieldChangeRequest->subject_id, $actor);

        Notification::send(
            $recipients,
            new FieldChangeRequestedNotification($fieldChangeRequest, $actor, $fieldLabel, $subjectLabel),
        );
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
