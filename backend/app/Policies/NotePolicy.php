<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Notes are an agnostic, cross-module component (spec 0052, D-9): this
 * policy governs ONLY the `notes.create` permission and the author-only
 * mutability rule (D-8) — it knows nothing about which host entity a note
 * is attached to. READ access is authorized separately, per-record, by
 * NoteEntityRegistry delegating to the host entity's own gate (D-6); it is
 * NOT modeled as a Policy ability here.
 *
 * abilities() is reduced to ['create', 'deleteAny'] (D-6, spec 0126 D-2):
 * viewAny/view/update/delete/export/import/viewActivity would generate
 * permissions nobody ever checks (reads are gated by the host entity,
 * writes by ownership below) — SyncPermissions derives the permission
 * catalog from THIS override (late static binding, BasePolicy::
 * permissions()), so only `notes.create`/`notes.deleteAny` are ever created.
 */
class NotePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'notes';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['create', 'deleteAny'];
    }

    /**
     * Author-only (D-8): no permission is consulted. The super-admin still
     * passes via Gate::before — existing platform behaviour, not a new
     * exception introduced here.
     *
     * $model is declared as the base Model (matching BasePolicy's signature
     * — PHP's contravariance rules forbid narrowing it to Note here); the
     * instanceof guard is what makes that widened signature safe again.
     */
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Note && $model->user_id === $user->id;
    }

    /**
     * Author-only, OR `notes.deleteAny` (spec 0126, D-2): the permission
     * only clears the OWNERSHIP gate here — NoteService::delete still
     * re-checks that the actor can read the note's host record before
     * actually deleting it, exactly like update() already does for its own
     * mutation.
     */
    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Note
            && ($model->user_id === $user->id || $user->can($this->permission('deleteAny')));
    }
}
