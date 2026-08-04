<?php

declare(strict_types=1);

namespace App\Services\FieldChangeRequests;

use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Notifications\FieldChangeRequestResolvedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Shared by FieldChangeRequestApprover and FieldChangeRequestRejecter (spec
 * 0078, D-3/AC-026): notifies the requester of the outcome, precomputing the
 * same field/subject presentation FieldChangeRequestCreator computes for the
 * OTHER notification — kept here once rather than duplicated in both
 * services.
 */
final class FieldChangeRequestResolutionNotifier
{
    public function __construct(
        private readonly ProtectedFieldRegistry $registry,
        private readonly FieldChangeRequestValueResolver $resolver,
    ) {}

    public function notify(FieldChangeRequest $fieldChangeRequest, User $handler): void
    {
        $protectedField = $this->registry->find($fieldChangeRequest->resource, $fieldChangeRequest->field);
        $fieldLabel = $protectedField?->fieldLabel ?? $fieldChangeRequest->field;
        $subjectLabel = $this->resolver->subjectLabelOrFallback(
            $fieldChangeRequest->resource,
            (int) $fieldChangeRequest->subject_id,
            $handler,
        );
        $subjectPath = $protectedField !== null
            ? $this->resolver->subjectPath($protectedField, $fieldChangeRequest->subject_id)
            : "/{$fieldChangeRequest->subject_id}";

        Notification::send(
            $fieldChangeRequest->requestedBy,
            new FieldChangeRequestResolvedNotification($fieldChangeRequest, $handler, $fieldLabel, $subjectLabel, $subjectPath),
        );
    }
}
