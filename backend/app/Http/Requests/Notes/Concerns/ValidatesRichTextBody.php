<?php

namespace App\Http\Requests\Notes\Concerns;

use App\RichText\RichTextPlainText;
use Illuminate\Contracts\Validation\Validator;

/**
 * D-2/D-5 content checks on a rich text body field, shared by StoreNoteRequest
 * and UpdateNoteRequest (spec 0128): `body`'s own `required|string` rule only
 * guards its TYPE, not its content — an HTML fragment with no visible text
 * and no image is empty (D-2, AC-008) and the VISIBLE text (not the raw HTML)
 * is capped at `rich_text.note_text_max` (D-5, AC-007).
 *
 * MUST run on the RAW submitted body, never a sanitized one: sanitizing a
 * brand new inline `data:` image strips its `src` before it has the
 * `data-attachment-id` RichTextImageProcessor assigns later, which would make
 * an image-only note look empty here (NoteService does the real sanitize
 * pass afterwards, D-3).
 */
trait ValidatesRichTextBody
{
    protected function validateBodyContent(Validator $validator, string $field = 'body'): void
    {
        if ($validator->errors()->has($field)) {
            return;
        }

        $body = $this->input($field);

        if (! is_string($body)) {
            return;
        }

        if (RichTextPlainText::isEmpty($body)) {
            $validator->errors()->add($field, __('validation.required', ['attribute' => $field]));

            return;
        }

        $max = (int) config('rich_text.note_text_max');

        if (mb_strlen(RichTextPlainText::toPlainText($body)) > $max) {
            $validator->errors()->add($field, __('validation.max.string', ['attribute' => $field, 'max' => $max]));
        }
    }
}
