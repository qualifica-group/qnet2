<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum embedded image size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Upper bound enforced server-side on every inline `data:` URI image
    | decoded from a rich text field, per image (spec 0128, D-5).
    |
    */

    'image_max_kb' => (int) env('RICH_TEXT_IMAGE_MAX_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Maximum new images per save
    |--------------------------------------------------------------------------
    |
    | How many inline `data:` URI images a single field may embed in one
    | save. Existing images already stored as attachments (referenced by
    | `data-attachment-id`) do not count against this limit.
    |
    */

    'max_new_images' => (int) env('RICH_TEXT_MAX_NEW_IMAGES', 10),

    /*
    |--------------------------------------------------------------------------
    | Note visible text maximum (characters)
    |--------------------------------------------------------------------------
    |
    | Applies to the VISIBLE text extracted from `notes.body` (RichTextPlainText),
    | not to the raw HTML — replaces the old max:5000 on the raw string.
    |
    */

    'note_text_max' => (int) env('RICH_TEXT_NOTE_TEXT_MAX', 5000),

    /*
    |--------------------------------------------------------------------------
    | Sanitized HTML maximum (characters)
    |--------------------------------------------------------------------------
    |
    | Applies to the sanitized HTML fragment after inline images have been
    | extracted into attachments and rewritten to `data-attachment-id`
    | references (the images themselves never inflate this length).
    |
    */

    'html_max' => (int) env('RICH_TEXT_HTML_MAX', 60000),

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME types for embedded images
    |--------------------------------------------------------------------------
    |
    | Server-side allowlist checked against the REAL bytes of a decoded
    | `data:` URI (finfo), never against the declared MIME in the URI itself.
    |
    */

    'allowed_image_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],

];
