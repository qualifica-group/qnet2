<?php

namespace App\Http\Requests\Notes;

use App\Http\Requests\Notes\Concerns\ValidatesNotableEntity;
use App\Http\Requests\Notes\Concerns\ValidatesQuoteScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/notes/read: the host entity and the `quote_scope` of
 * the thread the user is looking at, same shapes as GET /api/notes.
 *
 * Authorization is NOT handled here (read access to the host record, see
 * NoteService::markThreadRead via NoteEntityRegistry).
 */
class MarkNotesReadRequest extends FormRequest
{
    use ValidatesNotableEntity;
    use ValidatesQuoteScope;

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
            'quote_scope' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateQuoteScopeFormat($validator, $this->input('quote_scope')));
    }
}
