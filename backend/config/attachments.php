<?php

use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\DocumentBundle;
use App\Models\DocumentLayout;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OutboundEmail;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
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
        'application/zip',
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
        // Registry documents (spec 0173): same treatment as 'work_order' --
        // the alias is already in the global morph map, this only opens the
        // upload boundary.
        'registry' => Registry::class,
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
        // Document bundle files (spec 0175, D-7c): "Modello documenti", the
        // Commessa email composer's bulk-attach source. Alias already in the
        // global morph map (AppServiceProvider) for LogsModelActivity — this
        // entry only opens the upload boundary.
        'document_bundle' => DocumentBundle::class,
        // An email's own allegati (spec 0175, D-7/D-8): uploaded ONLY through
        // the nested work-orders/{workOrder}/emails/{email}/attachments
        // endpoints, never directly here — the `email_attachments` collection
        // is closed off to this generic boundary regardless (AttachmentPolicy
        // carve-out, same treatment as `rich_text`). Listed anyway because
        // AttachmentService/HasAttachments resolve the owner through this
        // same allowlist internally.
        'outbound_email' => OutboundEmail::class,
        // Purchase request and line documents (spec 0208, D-15): aliases
        // already in the global morph map (AppServiceProvider).
        'purchase_request' => PurchaseRequest::class,
        'purchase_request_line' => PurchaseRequestLine::class,
    ],

];
