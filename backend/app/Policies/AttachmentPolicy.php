<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\RichText\RichText;
use App\RichText\RichTextOwnerAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * Standard CRUD authorization for the Attachments resource, plus the
 * `rich_text` collection carve-out (spec 0128, D-6): those attachments are
 * images embedded in an owning record's rich text field, so they follow that
 * record's OWN read access instead of `attachments.*`, and are entirely
 * closed off to the generic write/browse endpoints — they are written and
 * removed only by the owner's own content (NoteService/TaskService/...),
 * never through POST/DELETE /api/attachments.
 *
 * Every other collection is untouched: maps to the `attachments.{ability}`
 * permission (registered by `php artisan permissions:sync`), resource-level,
 * no per-record boundary — the pre-existing, deliberately unfixed gap this
 * spec's scope note calls out (0128 out-of-scope).
 */
class AttachmentPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'attachments';
    }

    /**
     * The `rich_text` collection is never listable through the generic
     * index (D-6): neither the default "browse" query (excluded in the
     * controller) nor an explicit `collection=rich_text` filter, which would
     * otherwise let anyone with `attachments.viewAny` enumerate every
     * record's embedded images regardless of whether they can read the
     * owner.
     */
    public function viewAny(User $user, ?string $collection = null): bool
    {
        if ($collection === RichText::ATTACHMENT_COLLECTION) {
            return false;
        }

        return parent::viewAny($user);
    }

    /**
     * A `rich_text` attachment is readable iff the actor can read its
     * owner — independently of `attachments.view` (D-6). Every other
     * collection keeps the plain permission check.
     */
    public function view(User $user, Model $model): bool
    {
        if ($model instanceof Attachment && $model->collection === RichText::ATTACHMENT_COLLECTION) {
            return app(RichTextOwnerAccess::class)->canRead($user, $model->attachable);
        }

        return parent::view($user, $model);
    }

    /**
     * `rich_text` images are never created through the generic upload
     * endpoint (D-3/D-6): they are extracted from the owner's own HTML
     * field inside that field's own save transaction.
     */
    public function create(User $user, ?string $collection = null): bool
    {
        if ($collection === RichText::ATTACHMENT_COLLECTION) {
            return false;
        }

        return parent::create($user);
    }

    /**
     * `rich_text` images are never removed through the generic endpoint
     * (D-4/D-6): an unreferenced one is deleted automatically by
     * RichTextImageProcessor when the owner's own content no longer
     * references it.
     */
    public function delete(User $user, Model $model): bool
    {
        if ($model instanceof Attachment && $model->collection === RichText::ATTACHMENT_COLLECTION) {
            return false;
        }

        return parent::delete($user, $model);
    }
}
