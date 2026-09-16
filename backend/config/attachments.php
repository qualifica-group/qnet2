<?php

use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\DocumentLayout;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;

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
        // Task documents (spec 0117): the alias is ALREADY in the global
        // morph map (AppServiceProvider, spec 0101, added there for the
        // activity log), so this entry only opens the upload boundary -- it
        // does not name a new morph identity.
        'task' => Task::class,
        // Task template row documents (spec 0124, D-6): the source files
        // AttachmentService::copyTo() physically duplicates onto a generated
        // Task's own 'documents' collection. Alias already in the global
        // morph map (AppServiceProvider) for the same reason as 'task' above.
        'task_template_item' => TaskTemplateItem::class,
        // Work order documents (spec 0134): same treatment as 'task' -- the
        // alias is already in the global morph map, this only opens the
        // upload boundary.
        'work_order' => WorkOrder::class,
        // Note and task-template-header images embedded in a rich text field
        // (spec 0128, D-3): the record is its own attachment owner, under
        // the reserved `rich_text` collection (RichText::ATTACHMENT_COLLECTION).
        // Both aliases are already in the global morph map (AppServiceProvider)
        // for unrelated reasons (activity log / task-template-item sibling
        // above) — this entry only opens the upload boundary. Uploading
        // DIRECTLY into the `rich_text` collection through this boundary is
        // blocked regardless (AttachmentPolicy, D-6): only the owning
        // record's own content may create/remove those attachments.
        'note' => Note::class,
        'task_template' => TaskTemplate::class,
    ],

];
