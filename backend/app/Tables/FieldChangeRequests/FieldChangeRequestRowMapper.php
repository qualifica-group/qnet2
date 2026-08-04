<?php

declare(strict_types=1);

namespace App\Tables\FieldChangeRequests;

use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\FieldChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Row projection for the `field-change-requests` domain (spec 0078): turns a
 * FieldChangeRequest into the read-only browse row. `resource_label`/
 * `field_label` are resolved from config/field-change-requests.php (via
 * ProtectedFieldRegistry) keyed by the row's own (resource, field) pair —
 * the SAME source of truth the create/approve endpoints read, never
 * hardcoded here. `subject_label` reads the polymorphic subject's own
 * `name` when it has one (the only wired subject, Opportunity, does); a
 * future subject type with no `name` falls back to a plain "#<id>" instead
 * of breaking the row.
 */
final class FieldChangeRequestRowMapper
{
    public function __construct(private readonly ProtectedFieldRegistry $protectedFields) {}

    /**
     * @return array<string, mixed>
     */
    public function map(FieldChangeRequest $row): array
    {
        $protectedField = $this->protectedFields->find($row->resource, $row->field);

        return [
            'id' => $row->id,
            'resource_label' => $protectedField?->resourceLabel ?? $row->resource,
            'subject_label' => $this->subjectLabel($row),
            'field_label' => $protectedField?->fieldLabel ?? $row->field,
            'current_label' => $row->current_label,
            'requested_label' => $row->requested_label,
            'reason' => $row->reason,
            'requested_by' => $this->personSummary($row->requestedBy),
            'created_at' => $row->created_at,
            'status' => $row->status->value,
            'handled_by' => $this->personSummary($row->handledBy),
            'handled_at' => $row->handled_at,
            'handling_note' => $row->handling_note,
        ];
    }

    /**
     * The subject's own display name when it has one (Opportunity does); a
     * plain "#<id>" fallback otherwise, so an as-yet-unwired subject type
     * never breaks the row.
     */
    private function subjectLabel(FieldChangeRequest $row): string
    {
        $subject = $row->subject;

        if ($subject instanceof Model && is_string($subject->name ?? null) && $subject->name !== '') {
            return $subject->name;
        }

        return sprintf('#%d', $row->subject_id);
    }

    /**
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function personSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->avatarDataUri()];
    }
}
