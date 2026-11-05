<?php

declare(strict_types=1);

namespace App\Notes\Mentions;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\User;
use App\Notes\NoteEntityRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * The @mention lookup (spec 0052 D-10, extended by spec 0353 D-4). Scope
 * `record` is the people who can read the record; scope `extended` is every
 * active non-service user, each flagged with `in_record` (inside the
 * perimeter) and `has_module_access` (can open the record's module).
 * Pagination and `ids[]` hydration follow the for-select contract (ADR 0011)
 * within the chosen scope.
 */
final class MentionableUserDirectory
{
    public const string SCOPE_RECORD = 'record';

    public const string SCOPE_EXTENDED = 'extended';

    private const string SUPER_ADMIN_ROLE = 'super-admin';

    public function __construct(private readonly NoteEntityRegistry $registry) {}

    public function list(User $actor, string $entityType, int $entityId, string $scope, ForSelectQuery $query): ForSelectResult
    {
        // Step 1: the record must be readable; the extended scope also needs edit rights.
        $record = $this->registry->findRecord($entityType, $entityId);
        $this->registry->assertReadable($actor, $entityType, $record);

        $extended = $scope === self::SCOPE_EXTENDED;
        abort_if(
            $extended && ! $this->registry->mentionOutsideFor($actor, $entityType, $record)['allowed'],
            403,
            __('You cannot mention people outside this record.'),
        );

        // Step 2: page the scope's population, hydrating `ids[]` inside it.
        $population = ($extended ? $this->everyone() : $this->registry->mentionableUsersQuery($entityType, $record))
            ->select(['id', 'name', 'email'])
            ->with('avatar');

        $result = $this->page($population, $query);

        // Step 3: flag the page.
        $this->annotate($result->items, $entityType, $record, $extended);

        return $result;
    }

    private function everyone(): Builder
    {
        return User::query()->excludingServiceAccounts()->where('is_active', true);
    }

    private function page(Builder $population, ForSelectQuery $query): ForSelectResult
    {
        $filtered = clone $population;

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $filtered->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $window = $query->page($filtered, static fn ($ordered) => $ordered->orderBy('name')->orderBy('id'));
        $page = $window->items;

        if ($query->hasIds()) {
            $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

            if ($missingIds !== []) {
                $page = $page->concat((clone $population)->whereIn('id', $missingIds)->get());
            }
        }

        return $window->withItems($page);
    }

    /**
     * @param  Collection<int, mixed>  $users
     */
    private function annotate(Collection $users, string $entityType, Model $record, bool $extended): void
    {
        $insideIds = [];
        $accessIds = [];

        if ($extended && $users->isNotEmpty()) {
            $pageIds = $users->pluck('id')->all();
            $insideIds = $this->registry->mentionableUsersQuery($entityType, $record)->whereIn('id', $pageIds)->pluck('id')->all();
            $accessIds = $this->moduleAccessIds($pageIds, $this->registry->enlisterFor($entityType)?->modulePermission());
        }

        foreach ($users as $user) {
            $user->setAttribute('in_record', ! $extended || in_array($user->id, $insideIds, true));
            $user->setAttribute('has_module_access', ! $extended || in_array($user->id, $accessIds, true));
        }
    }

    /**
     * Super-admins plus holders of the module permission, one query for the
     * whole page. The permission is probed first because spatie's
     * `permission()` scope throws on a name no row carries.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>
     */
    private function moduleAccessIds(array $userIds, ?string $permission): array
    {
        $permissionExists = $permission !== null && Permission::query()
            ->where('name', $permission)
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->exists();

        return User::query()
            ->whereIn('id', $userIds)
            ->where(function (Builder $q) use ($permissionExists, $permission): void {
                $q->whereHas('roles', fn (Builder $role) => $role->where('name', self::SUPER_ADMIN_ROLE))
                    ->when($permissionExists, fn (Builder $q) => $q->orWhere(fn (Builder $holder) => $holder->permission($permission)));
            })
            ->pluck('id')
            ->all();
    }
}
