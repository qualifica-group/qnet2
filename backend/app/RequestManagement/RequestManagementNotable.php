<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Models\Opportunity;
use App\Models\User;
use App\Notes\Contracts\NotableEntity;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The `request-management` notable_types descriptor (spec 0052, D-9/D-10):
 * declares how the agnostic notes component may attach to an Opportunity
 * through THIS module's OWN authorization story (spec 0049) — read access
 * and the mentionable set both mirror the work panel's own gate
 * (RequestManagementScope, `request-management.viewAll`) verbatim; this
 * class never invents a separate rule.
 *
 * Lives in app/RequestManagement/ (this module's own namespace, alongside
 * AttributeSetResolver et al.), NOT app/Notes/: the module declares
 * how it wants to be treated by the notes component, the notes component
 * never names the module (AC-021). Resolved from the container by
 * App\Notes\NoteEntityRegistry via the class-string mapped in
 * config/notes.php ('request-management' => self::class) — pure data there,
 * config:cache-safe.
 */
final class RequestManagementNotable implements NotableEntity
{
    public function __construct(private readonly RequestManagementScope $scope) {}

    public function modelClass(): string
    {
        return Opportunity::class;
    }

    public function authorizeRead(User $user, Model $record): bool
    {
        if (! $user->can('request-management.view')) {
            return false;
        }

        /** @var Opportunity $record */
        return $user->can('request-management.viewAll') || $this->scope->isOperatorOf($user, $record);
    }

    /**
     * D-10: active users who hold `request-management.view` AND are either
     * this opportunity's GA2 Account Manager or hold
     * `request-management.viewAll`, plus super-admins. A plain `whereHas`
     * matching the role by NAME — not the `role()` scope, which resolves the
     * name via `Role::findByName()` and THROWS `RoleDoesNotExist` if that row
     * hasn't been created yet (e.g. before `roles:create-super-admin` ever
     * ran). This must never 500 the endpoint on an unseeded environment.
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        /** @var Opportunity $record */
        $managerIds = $record->managers()
            ->wherePivot('position', Opportunity::OPERATOR_MANAGER_POSITION)
            ->pluck('users.id');

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($managerIds): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->orWhere(function (Builder $canRead) use ($managerIds): void {
                        $canRead->permission('request-management.view')
                            ->where(function (Builder $access) use ($managerIds): void {
                                $access->whereIn('id', $managerIds)
                                    ->orWhere(function (Builder $viewAll): void {
                                        $viewAll->permission('request-management.viewAll');
                                    });
                            });
                    });
            });
    }

    public function label(Model $record): string
    {
        /** @var Opportunity $record */
        return (string) $record->name;
    }

    public function deepLinkPath(Model $record): string
    {
        return '/request-management/'.$record->getKey();
    }
}
