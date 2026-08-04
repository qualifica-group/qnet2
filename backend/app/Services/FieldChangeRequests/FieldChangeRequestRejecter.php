<?php

declare(strict_types=1);

namespace App\Services\FieldChangeRequests;

use App\Enums\FieldChangeRequestStatus;
use App\Models\FieldChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rejects a `pending` field-change-request (spec 0078): the record is NEVER
 * touched (AC-029) — only the request's own status/handling columns change.
 */
final class FieldChangeRequestRejecter
{
    public function __construct(private readonly FieldChangeRequestResolutionNotifier $notifier) {}

    /**
     * See FieldChangeRequestCreator::handle() for why the notification is
     * plain sequential code after DB::transaction() rather than
     * DB::afterCommit().
     */
    public function handle(FieldChangeRequest $fieldChangeRequest, User $rejecter, ?string $note): FieldChangeRequest
    {
        $fieldChangeRequest = DB::transaction(fn (): FieldChangeRequest => $this->reject($fieldChangeRequest, $rejecter, $note));

        $this->notifier->notify($fieldChangeRequest, $rejecter);

        return $fieldChangeRequest;
    }

    private function reject(FieldChangeRequest $fieldChangeRequest, User $rejecter, ?string $note): FieldChangeRequest
    {
        // Step 1: only a PENDING request may be decided (422, AC-031).
        abort_unless($fieldChangeRequest->status === FieldChangeRequestStatus::Pending, 422, __('This change request has already been handled.'));

        // Step 2: close the request — the subject record is untouched.
        $fieldChangeRequest->status = FieldChangeRequestStatus::Rejected;
        $fieldChangeRequest->handled_by_id = $rejecter->id;
        $fieldChangeRequest->handled_at = now();
        $fieldChangeRequest->handling_note = $note;
        $fieldChangeRequest->pending_key = null;
        $fieldChangeRequest->save();

        activity($fieldChangeRequest->getTable())
            ->performedOn($fieldChangeRequest)
            ->causedBy($rejecter)
            ->event('field_change_request.rejected')
            ->withProperties(['handling_note' => $note])
            ->log('Field change request rejected');

        return $fieldChangeRequest;
    }
}
