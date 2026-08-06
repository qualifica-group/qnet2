<?php

namespace App\Http\Requests\Notes\Concerns;

use App\Notes\NoteEntityRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared appartenenza check for a numeric quote id against the request's own
 * `entity_type`/`entity_id` host (spec 0085, D-1): used by StoreNoteRequest
 * (`quote_id`, AC-005) and IndexNoteRequest (`quote_scope`'s numeric form,
 * AC-013). Delegates the actual membership test to
 * NoteEntityRegistry::ownsQuote -> the host's own NotableEntity — this trait
 * only wires the FormRequest's validator/state around it, it never queries a
 * host module's scoping entity itself (AC-021/constraints).
 *
 * MUST run from a Validator::after() callback and read RAW input
 * (`$this->input()`), never `validated()`: the request has not finished
 * validating yet (same discipline as IndexNoteRequest's own cursor check).
 */
trait ValidatesQuoteOwnership
{
    /**
     * A missing/malformed host or an already-invalid $field is silently
     * skipped: a 404 (missing host) or the field's own `integer` rule
     * already own that failure, this only adds the appartenenza 422.
     */
    protected function validateQuoteOwnership(Validator $validator, string $field, int|string|null $rawQuoteId): void
    {
        if ($rawQuoteId === null || $rawQuoteId === '' || ! ctype_digit((string) $rawQuoteId)) {
            return;
        }

        if ($validator->errors()->hasAny(['entity_type', 'entity_id', $field])) {
            return;
        }

        $record = $this->resolveHostRecordOrNull();

        if ($record === null) {
            return;
        }

        $entityType = (string) $this->input('entity_type');

        if (! app(NoteEntityRegistry::class)->ownsQuote($entityType, $record, (int) $rawQuoteId)) {
            $validator->errors()->add($field, 'The selected quote does not belong to this entity.');
        }
    }

    private function resolveHostRecordOrNull(): ?Model
    {
        $entityType = (string) $this->input('entity_type');
        $entityId = $this->input('entity_id');

        if ($entityType === '' || ! is_numeric($entityId)) {
            return null;
        }

        try {
            return app(NoteEntityRegistry::class)->findRecord($entityType, (int) $entityId);
        } catch (ModelNotFoundException) {
            return null;
        }
    }
}
