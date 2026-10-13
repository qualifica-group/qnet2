<?php

declare(strict_types=1);

namespace App\Services\ProformaRequests;

use App\Models\ProformaRequest;
use App\Models\User;
use App\Notes\Contracts\NotableEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * The `proforma-requests` notable_types descriptor (spec 0193, D-6): attaches
 * the agnostic notes component to a proforma request. Accounting data has no
 * membership scope, so read access and the mentionable set are both the plain
 * `proforma-requests.view` permission (plus super-admins). Mirrors
 * App\Services\WorkOrders\WorkOrderNotable.
 */
final class ProformaRequestNotable implements NotableEntity
{
    private const VIEW_PERMISSION = 'proforma-requests.view';

    public function modelClass(): string
    {
        return ProformaRequest::class;
    }

    public function authorizeRead(User $user, Model $record): bool
    {
        return $user->can(self::VIEW_PERMISSION);
    }

    /**
     * authorizeRead() read from the other end. The permission is probed for
     * existence first because spatie's `permission()` scope throws on a name
     * no row carries (unsynced catalogue must answer, not 500).
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        $permissionExists = Permission::query()
            ->where('name', self::VIEW_PERMISSION)
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->exists();

        return User::query()
            ->excludingServiceAccounts()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($permissionExists): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->when($permissionExists, fn (Builder $reader) => $reader->orWhere(
                        fn (Builder $holder) => $holder->permission(self::VIEW_PERMISSION),
                    ));
            });
    }

    /**
     * A proforma request has no scoping unit of its own: `quote_id` stays
     * unwritable on its notes.
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

    public function label(Model $record): string
    {
        /** @var ProformaRequest $record */
        $record->loadMissing('workOrder:id,code,title');

        return "{$record->workOrder->code} — {$record->workOrder->title}";
    }

    public function deepLinkPath(Model $record, User $recipient, ?int $quoteId): ?string
    {
        return $this->authorizeRead($recipient, $record) ? '/proforma-requests/'.$record->getKey() : null;
    }
}
