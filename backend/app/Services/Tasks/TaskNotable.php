<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notes\Contracts\NotableEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * The `tasks` notable_types descriptor (spec 0117, D-3..D-7): declares how
 * the agnostic notes component may attach to a Task through THIS module's
 * OWN authorization story (spec 0101 D-9) — read access and the mentionable
 * set are both the SAME membership rule, read from opposite ends, and both
 * delegate to App\Services\Tasks\TaskVisibilityScope. This class never
 * restates that predicate: a second copy of it would drift at the first
 * change, and the table's rows and the thread's gate would then disagree.
 *
 * Lives in app/Services/Tasks/ (this module's own namespace, alongside
 * TaskVisibilityScope et al.), NOT app/Notes/: the module declares how it
 * wants to be treated by the notes component, the notes component never
 * names the module (spec 0052, AC-021 — enforced by NoteAgnosticismTest).
 * Resolved from the container by App\Notes\NoteEntityRegistry via the
 * class-string mapped in config/notes.php ('tasks' => self::class) — pure
 * data there, config:cache-safe.
 *
 * Unlike TaskVisibilityScope/TaskAbilityResolver this class is an ordinary
 * container-resolved object, not a static one: nothing in the
 * `permissions:sync` path ever constructs it.
 */
final class TaskNotable implements NotableEntity
{
    public function modelClass(): string
    {
        return Task::class;
    }

    /**
     * D-4: the resource permission AND the membership scope, in that order —
     * the same two conditions TaskPolicy::view() applies. The rule NARROWS
     * and never widens: being an assegnatario does not grant `tasks.view`
     * (spec 0101, AC-062), so a member without the resource permission reads
     * nothing.
     */
    public function authorizeRead(User $user, Model $record): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        /** @var Task $record */
        return TaskVisibilityScope::isVisibleTo($user, $record);
    }

    /**
     * D-5: active users who hold `tasks.view` AND reach this Task — they are
     * one of its four record roles (creatore, richiedente, assegnatario,
     * osservatore), they hold `tasks.viewAll`, or they hold `tasks.viewSite`
     * and share a Sede with one of its assignees (spec 0148) — plus
     * super-admins. It is
     * authorizeRead() read from the other end: there the actor is known and
     * the Task is queried, here the Task is known and the actors are. Any
     * gap between the two would mean either mentioning someone who then gets
     * a 403, or a colleague who reads the thread and can never be called
     * into it.
     *
     * The membership ids are read from the record rather than re-derived
     * with a join: the four roles are two scalar columns and two pivots the
     * caller has usually already loaded.
     *
     * Both permission branches are probed for existence first (name AND
     * guard, exactly what spatie's `permission()` scope will resolve): that
     * scope goes through `Permission::findByName()` and THROWS
     * `PermissionDoesNotExist` on a name no row carries. On an environment
     * where `permissions:sync` has not run yet this endpoint must answer
     * "only super-admins", never 500. Same reason the super-admin branch
     * matches the role by NAME through `whereHas` instead of the `role()`
     * scope, which throws `RoleDoesNotExist` the same way.
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        /** @var Task $record */
        $memberIds = $this->memberIds($record);
        $siteIds = $this->assigneeSiteIds($record);
        $viewExists = $this->permissionExists('tasks.view');
        $viewAllExists = $this->permissionExists(TaskVisibilityScope::VIEW_ALL_PERMISSION);
        $viewSiteExists = $siteIds !== [] && $this->permissionExists(TaskVisibilityScope::VIEW_SITE_PERMISSION);

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($memberIds, $siteIds, $viewExists, $viewAllExists, $viewSiteExists): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->when($viewExists, function (Builder $canRead) use ($memberIds, $siteIds, $viewAllExists, $viewSiteExists): void {
                        $canRead->orWhere(function (Builder $reader) use ($memberIds, $siteIds, $viewAllExists, $viewSiteExists): void {
                            $reader->permission('tasks.view')
                                ->where(function (Builder $access) use ($memberIds, $siteIds, $viewAllExists, $viewSiteExists): void {
                                    $access->whereIn('id', $memberIds)
                                        ->when($viewAllExists, function (Builder $tiers): void {
                                            $tiers->orWhere(function (Builder $viewAll): void {
                                                $viewAll->permission(TaskVisibilityScope::VIEW_ALL_PERMISSION);
                                            });
                                        })
                                        ->when($viewSiteExists, function (Builder $tiers) use ($siteIds): void {
                                            $tiers->orWhere(function (Builder $bySite) use ($siteIds): void {
                                                $bySite->permission(TaskVisibilityScope::VIEW_SITE_PERMISSION)
                                                    ->whereHas(
                                                        'employment.operationalSites',
                                                        fn (Builder $sites) => $sites->whereIn('operational_sites.id', $siteIds),
                                                    );
                                            });
                                        });
                                });
                        });
                    });
            });
    }

    /**
     * D-6: a Task has no scoping unit of its own — the concept the interface
     * calls a "quote" simply does not exist here. Answering false always is
     * what makes `quote_id` unwritable on a Task's note (the notes core
     * validates every submitted id against this) and what keeps the
     * selectors unmounted client-side.
     */
    public function ownsQuote(Model $record, int $quoteId): bool
    {
        return false;
    }

    /**
     * D-6: see ownsQuote(). An empty list is the notes component's own
     * signal that this host has no scoping units, so `NotesSection` renders
     * neither the filter nor the destination selector.
     *
     * @return array<int, array{id: int, code: string, title: string}>
     */
    public function quoteScopes(Model $record): array
    {
        return [];
    }

    public function label(Model $record): string
    {
        /** @var Task $record */
        return (string) $record->title;
    }

    /**
     * D-7: the Task detail, `/tasks/{id}` (generated from the module
     * registry by buildModuleRoutes()). Per-recipient and nullable because
     * the screen answers to BOTH `tasks.view` and the membership scope: a
     * mention can legitimately reach someone the Task itself is closed to,
     * and a link that lands on a 403 is worse than no link at all — the
     * caller then appends the request-access sentence instead.
     *
     * $quoteId is ignored: a Task's notes are never scoped (D-6).
     */
    public function deepLinkPath(Model $record, User $recipient, ?int $quoteId): ?string
    {
        /** @var Task $record */
        return $this->authorizeRead($recipient, $record) ? '/tasks/'.$record->getKey() : null;
    }

    /**
     * The four record roles as a flat id list: two scalar columns and the
     * two membership pivots.
     *
     * @return array<int, int>
     */
    private function memberIds(Task $task): array
    {
        $ids = [
            $task->creator_id,
            $task->requester_id,
            ...$task->assignees()->pluck('users.id')->all(),
            ...$task->watchers()->pluck('users.id')->all(),
        ];

        return array_values(array_unique(array_filter(
            array_map(static fn ($id): ?int => $id === null ? null : (int) $id, $ids),
        )));
    }

    /**
     * Every Sede of the Task's assignees, physical or remote (spec 0148): the
     * set a `viewSite` reader must share one of.
     *
     * @return array<int, int>
     */
    private function assigneeSiteIds(Task $task): array
    {
        return $task->assignees()
            ->join('employment_profiles', 'employment_profiles.user_id', '=', 'users.id')
            ->join('employment_profile_operational_site', 'employment_profile_operational_site.employment_profile_id', '=', 'employment_profiles.id')
            ->distinct()
            ->pluck('employment_profile_operational_site.operational_site_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Probed on the name AND the guard spatie's own `permission()` scope
     * will resolve (`Guard::getDefaultName(User::class)`): the same name can
     * carry one row per guard, and a hit on the wrong one would leave the
     * branch matching nobody.
     */
    private function permissionExists(string $name): bool
    {
        return Permission::query()
            ->where('name', $name)
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->exists();
    }
}
