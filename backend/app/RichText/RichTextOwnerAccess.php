<?php

declare(strict_types=1);

namespace App\RichText;

use App\Models\Note;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Notes\NoteEntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Whether $user may READ $owner, the polymorphic record a `rich_text`
 * attachment belongs to (spec 0128, D-6): AttachmentPolicy::view() defers
 * here for that collection instead of the plain `attachments.view`
 * permission, because the image is only ever reachable through the owning
 * record's own content.
 *
 * One branch per owner alias config('attachments.attachable_types') admits
 * for `rich_text` (D-3): Note delegates to the SAME host-entity read gate
 * `App\Notes\NoteEntityRegistry` already applies to the note's own body
 * (D-6's "Nota -> lettura del record ospite"); Task/TaskTemplate/
 * TaskTemplateItem delegate to their own Policy `view` ability — an
 * unrecognised owner type reads as false, never as an exception escaping to
 * the controller.
 */
final class RichTextOwnerAccess
{
    public function __construct(private readonly NoteEntityRegistry $notes) {}

    public function canRead(User $user, ?Model $owner): bool
    {
        return match (true) {
            $owner === null => false,
            $owner instanceof Note => $this->canReadNote($user, $owner),
            $owner instanceof Task, $owner instanceof TaskTemplate => $user->can('view', $owner),
            $owner instanceof TaskTemplateItem => $user->can('view', $owner->template),
            default => false,
        };
    }

    /**
     * A note reads through its OWN host record (D-6), not through any
     * permission of the note itself (NotePolicy deliberately has no `view`
     * ability, spec 0052 D-9). A soft-deleted/missing host — `notable`
     * resolves through a MorphTo, so a trashed host is simply not found —
     * and an unregistered host type (`entityTypeForModel` aborts 422) both
     * mean "cannot tell this is readable", so both read as false.
     */
    private function canReadNote(User $user, Note $note): bool
    {
        $host = $note->notable;

        if ($host === null) {
            return false;
        }

        try {
            $entityType = $this->notes->entityTypeForModel($host);
            $this->notes->assertReadable($user, $entityType, $host);

            return true;
        } catch (HttpException) {
            return false;
        }
    }
}
