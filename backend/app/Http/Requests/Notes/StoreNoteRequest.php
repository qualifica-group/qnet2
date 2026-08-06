<?php

namespace App\Http\Requests\Notes;

use App\DataObjects\Notes\CreateNoteData;
use App\Http\Requests\Notes\Concerns\ValidatesNotableEntity;
use App\Http\Requests\Notes\Concerns\ValidatesQuoteOwnership;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/notes (spec 0052 data_contract): the host entity
 * (ValidatesNotableEntity), the body, an optional parent, an optional
 * `quote_id` (spec 0085, D-1) and the raw mentions array.
 *
 * `parent_id` only checks the note exists (not soft-deleted): the D-7
 * single-level normalization and the "different host record" 422 both need
 * the resolved host record, so they run in NoteThreadResolver, not here.
 * Mention/body coherence (D-12) and the D-10 mentionable-set boundary run in
 * MentionValidator for the SAME reason. `quote_id` appartenenza (AC-005) is
 * validated here (ValidatesQuoteOwnership) since the FormRequest already has
 * everything it needs — the 422 must never let a note be created at all.
 * A `quote_id` sent alongside `parent_id` is still validated (a client
 * cannot bypass the 422 for a nonexistent/foreign quote by nesting a reply);
 * NoteService is the one that then IGNORES it in favour of the root's own
 * `quote_id` (D-4).
 *
 * Authorization is NOT handled here: `notes.create` is checked in the
 * controller via NotePolicy, the AND'd read access to the host record inside
 * NoteService::create (D-6).
 */
class StoreNoteRequest extends FormRequest
{
    use ValidatesNotableEntity;
    use ValidatesQuoteOwnership;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->notableEntityRules(), [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('notes', 'id')->whereNull('deleted_at')],
            'quote_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'mentions' => ['sometimes', 'array'],
            'mentions.*' => ['integer'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateQuoteOwnership($validator, 'quote_id', $this->input('quote_id'));
        });
    }

    public function toData(): CreateNoteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new CreateNoteData(
            entityType: (string) $validated['entity_type'],
            entityId: (int) $validated['entity_id'],
            body: (string) $validated['body'],
            parentId: isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            quoteId: isset($validated['quote_id']) ? (int) $validated['quote_id'] : null,
            mentionIds: array_map('intval', $validated['mentions'] ?? []),
        );
    }
}
