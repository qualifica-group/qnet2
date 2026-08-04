<?php

declare(strict_types=1);

namespace App\Services\FieldChangeRequests;

use App\Enums\FieldChangeRequestStatus;
use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Services\TableCellUpdateService;
use Illuminate\Support\Facades\DB;

/**
 * Approves a `pending` field-change-request (spec 0078): applies the
 * requested value to the record and closes the request, atomically.
 *
 *   1. only a PENDING request may be decided (422, AC-031)
 *   2. re-resolve the field + record (same guards as creation)
 *   3. conflict guard (D-4): the field must still hold EXACTLY the value
 *      snapshotted at creation time, or this is a 409 and NOTHING is
 *      written — the request stays `pending` (AC-030)
 *   4. apply the value through TableCellUpdateService, acting AS THE
 *      APPROVER (D-7) — its own guard chain is what turns a missing
 *      `{resource}.update`/field permission into a 403 (AC-033), and an
 *      invalid value (e.g. a deleted Fonte) into a 422 (AC-035)
 *   5. close the request (`approved`, `handled_by_id`, `handled_at`,
 *      `handling_note`, `pending_key` nulled — D-5/AC-021)
 */
final class FieldChangeRequestApprover
{
    public function __construct(
        private readonly ProtectedFieldRegistry $registry,
        private readonly FieldChangeRequestValueResolver $resolver,
        private readonly TableCellUpdateService $cellUpdateService,
        private readonly FieldChangeRequestResolutionNotifier $notifier,
    ) {}

    /**
     * See FieldChangeRequestCreator::handle() for why the notification is
     * plain sequential code after DB::transaction() rather than
     * DB::afterCommit().
     */
    public function handle(FieldChangeRequest $fieldChangeRequest, User $approver, ?string $note): FieldChangeRequest
    {
        $fieldChangeRequest = DB::transaction(fn (): FieldChangeRequest => $this->approve($fieldChangeRequest, $approver, $note));

        $this->notifier->notify($fieldChangeRequest, $approver);

        return $fieldChangeRequest;
    }

    private function approve(FieldChangeRequest $fieldChangeRequest, User $approver, ?string $note): FieldChangeRequest
    {
        // Step 1
        abort_unless($fieldChangeRequest->status === FieldChangeRequestStatus::Pending, 422, __('This change request has already been handled.'));

        // Step 2
        $protectedField = $this->registry->find($fieldChangeRequest->resource, $fieldChangeRequest->field);
        abort_if($protectedField === null, 422, __('This field is not open to change requests.'));

        $definition = $this->resolver->definitionFor($fieldChangeRequest->resource);
        $record = $this->resolver->record($definition, $fieldChangeRequest->subject_id);

        // Step 3
        $liveValue = $this->resolver->currentValue($record, $protectedField);
        abort_if(
            ! $this->resolver->sameValue($liveValue, $fieldChangeRequest->current_value),
            409,
            __('The field has changed since this request was proposed.'),
        );

        // Step 4
        $this->cellUpdateService->update(
            $definition,
            $approver,
            (int) $fieldChangeRequest->subject_id,
            $protectedField->column,
            $fieldChangeRequest->requested_value,
        );

        // Step 5
        $fieldChangeRequest->status = FieldChangeRequestStatus::Approved;
        $fieldChangeRequest->handled_by_id = $approver->id;
        $fieldChangeRequest->handled_at = now();
        $fieldChangeRequest->handling_note = $note;
        $fieldChangeRequest->pending_key = null;
        $fieldChangeRequest->save();

        activity($fieldChangeRequest->getTable())
            ->performedOn($fieldChangeRequest)
            ->causedBy($approver)
            ->event('field_change_request.approved')
            ->withProperties(['handling_note' => $note])
            ->log('Field change request approved');

        return $fieldChangeRequest;
    }
}
