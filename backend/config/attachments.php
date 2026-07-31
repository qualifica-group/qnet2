<?php

use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\DocumentLayout;
use App\Models\Opportunity;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk (config/filesystems.php) where attachment binaries are
    | stored. Defaults to the private "local" disk: files are NOT publicly
    | served and are only reachable through the authenticated download endpoint.
    |
    */

    'disk' => env('ATTACHMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Base directory
    |--------------------------------------------------------------------------
    |
    | Directory prefix (within the disk) under which files are stored.
    |
    */

    'directory' => 'attachments',

    /*
    |--------------------------------------------------------------------------
    | Maximum size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Upper bound enforced server-side on every upload. Defaults to 10 MB.
    |
    */

    'max_size' => (int) env('ATTACHMENTS_MAX_SIZE', 10240),

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME types
    |--------------------------------------------------------------------------
    |
    | Server-side allowlist of accepted MIME types. The frontend is never the
    | source of truth: an upload whose detected MIME type is not listed here is
    | rejected. Empty array = no MIME restriction (size limit still applies).
    |
    */

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachable types (polymorphic allowlist)
    |--------------------------------------------------------------------------
    |
    | Maps a public, stable alias the client may send (attachable_type) to the
    | concrete owning model class. This is the security boundary for the
    | polymorphic relation: only models listed here can be targeted by an
    | upload, so a request can never attach a file to an arbitrary class.
    |
    | The stable alias is persisted in the morph column: a global morph map is
    | enforced (see AppServiceProvider::boot), so getMorphClass() returns the
    | alias rather than the FQCN. The same alias is also the wire format.
    |
    */

    'attachable_types' => [
        'user' => User::class,
        'company_site' => CompanySite::class,
        'opportunity' => Opportunity::class,
        'document_layout' => DocumentLayout::class,
        // Contract documents (spec 0072): attachable_type = 'contract'. Quote
        // documents are never re-attached here — the quote's own layout
        // documents stay tied to the quote (spec 0070); a contract's
        // "Documenti opportunita'" section is a read-only mount of the
        // existing 'opportunity' alias, not a second alias.
        'contract' => Contract::class,
    ],

];
