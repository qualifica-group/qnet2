<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Notes\Contracts\NotableEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * The `work-orders` notable_types descriptor (spec 0134, D-1/D-2): attaches
 * the agnostic notes component to a Commessa through THIS module's own
 * authorization story. Read access and the mentionable set are the same
 * membership rule read from opposite ends, both delegating to
 * WorkOrderVisibilityScope — never a restated copy of its predicate.
 *
 * Lives in the module's own namespace, never in app/Notes/ (spec 0052,
 * AC-021): the module declares how it wants to be treated, the notes core
 * never names the module. Mirrors App\Services\Tasks\TaskNotable.
 */
final class WorkOrderNotable implements NotableEntity
{
    private const VIEW_PERMISSION = 'work-orders.view';

    public function modelClass(): string
    {
        return WorkOrder::class;
    }

    /**
     * The same two conditions WorkOrderPolicy::view() applies: the resource
     * permission AND the membership scope. The scope narrows, it never grants.
     */
    public function authorizeRead(User $user, Model $record): bool
    {
        if (! $user->can(self::VIEW_PERMISSION)) {
            return false;
        }

        /** @var WorkOrder $record */
        return WorkOrderVisibilityScope::isVisibleTo($user, $record);
    }

    /**
     * authorizeRead() read from the other end: active users holding
     * `work-orders.view` who are Responsabili/Partecipanti or hold
     * `work-orders.viewAll`, plus (spec 0316) holders of `work-orders.viewSite`
     * belonging to an operational site of one of the commessa's source offers,
     * plus super-admins. Both permission branches are
     * probed for existence first because spatie's `permission()` scope throws
     * on a name no row carries (unsynced catalogue must answer, not 500).
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        /** @var WorkOrder $record */
        $memberIds = $this->memberIds($record);
        $viewExists = $this->permissionExists(self::VIEW_PERMISSION);
        $viewAllExists = $this->permissionExists(WorkOrderVisibilityScope::VIEW_ALL_PERMISSION);
        $siteIds = $this->sourceSiteIds($record);
        $siteExists = $siteIds !== [] && $this->permissionExists(WorkOrderVisibilityScope::VIEW_SITE_PERMISSION);

        return User::query()
            ->excludingServiceAccounts()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($memberIds, $viewExists, $viewAllExists, $siteExists, $siteIds): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->when($viewExists, function (Builder $canRead) use ($memberIds, $viewAllExists, $siteExists, $siteIds): void {
                        $canRead->orWhere(function (Builder $reader) use ($memberIds, $viewAllExists, $siteExists, $siteIds): void {
                            $reader->permission(self::VIEW_PERMISSION)
                                ->where(function (Builder $access) use ($memberIds, $viewAllExists, $siteExists, $siteIds): void {
                                    $access->whereIn('id', $memberIds)
                                        ->when($viewAllExists, function (Builder $tiers): void {
                                            $tiers->orWhere(function (Builder $viewAll): void {
                                                $viewAll->permission(WorkOrderVisibilityScope::VIEW_ALL_PERMISSION);
                                            });
                                        })
                                        ->when($siteExists, function (Builder $tiers) use ($siteIds): void {
                                            $tiers->orWhere(function (Builder $bySite) use ($siteIds): void {
                                                $bySite->permission(WorkOrderVisibilityScope::VIEW_SITE_PERMISSION)
                                                    ->whereHas('employment.operationalSites', fn (Builder $sites) => $sites->whereIn('operational_sites.id', $siteIds));
                                            });
                                        });
                                });
                        });
                    });
            });
    }

    /**
     * A Commessa has no scoping unit of its own: answering false always makes
     * `quote_id` unwritable on its notes and keeps the selectors unmounted.
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
        /** @var WorkOrder $record */
        return "{$record->code} — {$record->title}";
    }

    /**
     * Per-recipient: a mention can reach someone the Commessa is closed to,
     * and a link that lands on a 403 is worse than no link at all.
     */
    public function deepLinkPath(Model $record, User $recipient, ?int $quoteId): ?string
    {
        /** @var WorkOrder $record */
        return $this->authorizeRead($recipient, $record) ? '/work-orders/'.$record->getKey() : null;
    }

    /**
     * Responsabili and Partecipanti as a flat id list.
     *
     * @return array<int, int>
     */
    private function memberIds(WorkOrder $workOrder): array
    {
        $ids = [
            ...$workOrder->supervisors()->pluck('users.id')->all(),
            ...$workOrder->participants()->pluck('users.id')->all(),
        ];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Operational sites of the commessa's source offers (`quote_id`, or its
     * pivot lines' offers): the site-tier counterpart of memberIds().
     *
     * @return array<int, int>
     */
    private function sourceSiteIds(WorkOrder $workOrder): array
    {
        $viaLines = DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->join('quotes', 'quotes.id', '=', 'quote_lines.quote_id')
            ->where('quote_line_work_order.work_order_id', $workOrder->getKey())
            ->pluck('quotes.operational_site_id');

        $direct = DB::table('quotes')->where('id', $workOrder->quote_id)->pluck('operational_site_id');

        return $direct->merge($viaLines)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Probed on the name AND the guard spatie's `permission()` scope resolves.
     */
    private function permissionExists(string $name): bool
    {
        return Permission::query()
            ->where('name', $name)
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->exists();
    }
}
