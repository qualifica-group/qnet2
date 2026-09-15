<?php

namespace App\Http\Requests\Notes;

use App\DataObjects\Notes\UpdateNoteData;
use App\Http\Requests\Notes\Concerns\ValidatesRichTextBody;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /api/notes/{note} (spec 0052/0128 data_contract): only
 * `body` and `mentions` are writable — `entity_type`/`entity_id`/`parent_id`
 * are not modifiable and simply ignored if sent.
 *
 * `body` is a RichTextHtml (spec 0128, D-1): only its type is checked by the
 * `required|string` rule, its CONTENT (empty/too long, D-2/D-5) by
 * ValidatesRichTextBody — same split as StoreNoteRequest, see there for why
 * sanitizing/D-7/D-3 stay in NoteService.
 *
 * Authorization is NOT handled here: ownership (D-8, `notes.user_id ===
 * auth id`) is checked in the controller via NotePolicy::update, and the
 * "still readable" re-check runs inside NoteService::update.
 */
class UpdateNoteRequest extends FormRequest
{
    use ValidatesRichTextBody;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'mentions' => ['sometimes', 'array'],
            'mentions.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateBodyContent($validator);
        });
    }

    public function toData(): UpdateNoteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new UpdateNoteData(
            body: (string) $validated['body'],
            mentionIds: array_map('intval', $validated['mentions'] ?? []),
        );
    }
}
