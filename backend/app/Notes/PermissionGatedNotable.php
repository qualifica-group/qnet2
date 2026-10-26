<?php

declare(strict_types=1);

namespace App\Notes;

use App\Models\User;
use App\Notes\Contracts\NotableEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * A NotableEntity whose thread is readable by whoever holds ONE view permission
 * of the host module (spec 0312, D-2): the read gate, the mentionable set (that
 * gate read from the other end) and the per-recipient deep link all derive from
 * it. A host with a record-level visibility rule on top narrows both sides
 * through isVisibleTo() / narrowReaders(), never by restating the permission.
 *
 * Agnostic on purpose (spec 0052, AC-021): the subclass, in the host module's
 * own namespace, names the permission, the model and the screen.
 */
abstract class PermissionGatedNotable implements NotableEntity
{
    abstract protected function viewPermission(): string;

    /**
     * SPA path of $record's detail, where its notes tab lives.
     */
    abstract protected function detailPath(Model $record): string;

    public function authorizeRead(User $user, Model $record): bool
    {
        return $user->can($this->viewPermission()) && $this->isVisibleTo($user, $record);
    }

    /**
     * authorizeRead() read from the other end: active users holding the view
     * permission (narrowed by narrowReaders()), plus super-admins. The
     * permission is probed for existence first because spatie's `permission()`
     * scope throws on a name no row carries (unsynced catalogue must answer,
     * not 500).
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        $permission = $this->viewPermission();
        $permissionExists = $this->permissionExists($permission);

        return User::query()
            ->excludingServiceAccounts()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($permission, $permissionExists, $record): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->when($permissionExists, fn (Builder $readers) => $readers->orWhere(
                        function (Builder $holder) use ($permission, $record): void {
                            $holder->permission($permission);
                            $this->narrowReaders($holder, $record);
                        },
                    ));
            });
    }

    /**
     * No scoping unit: `quote_id` stays unwritable and the selectors unmounted.
     */
    public function ownsQuote(Model $record, int $quoteId): bool
    {
        return false;
    }

    /**
     * @return array<int, array{id: int, code: string, title: string}>
     */
    public function quoteScopes(Model $record): array
    {
        return [];
    }

    /**
     * Per-recipient: a link that lands on a 403 is worse than no link at all.
     */
    public function deepLinkPath(Model $record, User $recipient, ?int $quoteId): ?string
    {
        return $this->authorizeRead($recipient, $record) ? $this->detailPath($record) : null;
    }

    /**
     * Record-level rule on top of the permission; none by default.
     */
    protected function isVisibleTo(User $user, Model $record): bool
    {
        return true;
    }

    /**
     * The same record-level rule as isVisibleTo(), over the permission holders.
     */
    protected function narrowReaders(Builder $holders, Model $record): void {}

    /**
     * Probed on the name AND the guard spatie's `permission()` scope resolves.
     */
    protected function permissionExists(string $name): bool
    {
        return Permission::query()
            ->where('name', $name)
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->exists();
    }
}
