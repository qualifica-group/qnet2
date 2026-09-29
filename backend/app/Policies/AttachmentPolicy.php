<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\RichText\RichText;
use App\RichText\RichTextOwnerAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * Standard CRUD authorization for the Attachments resource, plus two
 * collection carve-outs entirely closed off to the generic write/browse
 * endpoints:
 *
 * - `rich_text` (spec 0128, D-6): images embedded in an owning record's rich
 *   text field, readable through that record's OWN read access instead of
 *   `attachments.*`, written/removed only by the owner's own content.
 * - `email_attachments` (spec 0175, D-8): an OutboundEmail's own allegati,
 *   closed outright even to a holder of `attachments.*` — reachable only
 *   through the nested `work-orders/{workOrder}/emails/{email}/attachments/*`
 *   endpoints, authorized on the owning Commessa.
 *
 * Every other collection is untouched: maps to the `attachments.{ability}`
 * permission (registered by `php artisan permissions:sync`), resource-level,
 * no per-record boundary — the pre-existing, deliberately unfixed gap spec
 * 0128's scope note calls out (0128 out-of-scope).
 */
class AttachmentPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'attachments';
    }

    /**
     * Neither carve-out collection is ever listable through the generic
     * index: neither the default "browse" query (excluded in the
     * controller) nor an explicit `collection=...` filter, which would
     * otherwise let anyone with `attachments.viewAny` enumerate every
     * record's embedded images or every email's allegati regardless of
     * whether they can read the owner.
     */
    public function viewAny(User $user, ?string $collection = null): bool
    {
        if ($this->isClosedCollection($collection)) {
            return false;
        }

        return parent::viewAny($user);
    }

    /**
     * A `rich_text` attachment is readable iff the actor can read its
     * owner — independently of `attachments.view` (D-6). An
     * `email_attachments` one is NEVER readable here (D-8): it is only
     * ever served by the nested email attachment download endpoint, which
     * authorizes on the owning Commessa directly. Every other collection
     * keeps the plain permission check.
     */
    public function view(User $user, Model $model): bool
    {
        if ($model instanceof Attachment && $model->collection === RichText::ATTACHMENT_COLLECTION) {
            return app(RichTextOwnerAccess::class)->canRead($user, $model->attachable);
        }

        if ($model instanceof Attachment && $model->collection === OutboundEmail::ATTACHMENT_COLLECTION) {
            return false;
        }

        return parent::view($user, $model);
    }

    /**
     * Neither carve-out collection is ever created through the generic
     * upload endpoint: `rich_text` images are extracted from the owner's own
     * HTML field (D-3/D-6); `email_attachments` are written only by the
     * nested email attachment upload/import endpoints (D-7/D-8).
     */
    public function create(User $user, ?string $collection = null): bool
    {
        if ($this->isClosedCollection($collection)) {
            return false;
        }

        return parent::create($user);
    }

    /**
     * Neither carve-out collection is ever removed through the generic
     * endpoint: an unreferenced `rich_text` image is deleted automatically by
     * RichTextImageProcessor (D-4/D-6); an `email_attachments` row is
     * removed only through the nested email attachment endpoint, which also
     * enforces the draft-only state rule (D-8).
     */
    public function delete(User $user, Model $model): bool
    {
        if ($model instanceof Attachment && $this->isClosedCollection($model->collection)) {
            return false;
        }

        return parent::delete($user, $model);
    }

    private function isClosedCollection(?string $collection): bool
    {
        return in_array($collection, [RichText::ATTACHMENT_COLLECTION, OutboundEmail::ATTACHMENT_COLLECTION], true);
    }
}
