<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF conversion (spec 0070)
    |--------------------------------------------------------------------------
    |
    | The quote document and the layout preview are rendered as `.docx` by
    | PhpWord and then converted to PDF by a headless LibreOffice process
    | (App\Services\DocumentLayouts\Rendering\DocxToPdfConverter): LibreOffice
    | reads OOXML natively, so tables, images, headers and page breaks survive
    | the conversion — which a PhpWord-to-HTML-to-PDF writer does not.
    |
    | `binary` is the LibreOffice executable: a PATH-resolvable name or an
    | absolute path (macOS: /Applications/LibreOffice.app/Contents/MacOS/soffice).
    | It is a DEPLOYMENT REQUIREMENT — without it the endpoints answer 500,
    | never a silently degraded document.
    |
    | `timeout` bounds the conversion process in seconds: a hung LibreOffice
    | must fail the request, not hold the PHP worker forever.
    |
    */

    'pdf' => [
        'binary' => env('LIBREOFFICE_BINARY', 'soffice'),
        'timeout' => (int) env('LIBREOFFICE_TIMEOUT', 60),
    ],

];
