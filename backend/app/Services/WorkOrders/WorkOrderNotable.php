<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Notes\Contracts\EnlistsMentionedUsers;
use App\Notes\Contracts\NotableEntity;
use App\Support\ManagerPositions;
use App\Support\PeopleChangeLog;
use App\Support\PositionalPivotSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * The `work-orders` notable_types descriptor (spec 0134, D-1/D-2): attaches
 * the agnostic notes component to a Commessa through THIS module's own
 * authorization story. Read access and readersQuery() are the same
 * membership rule read from opposite ends, both delegating to
 * WorkOrderVisibilityScope; the mentionable set is the team among those readers.
 *
 * Lives in the module's own namespace, never in app/Notes/ (spec 0052,
 * AC-021): the module declares how it wants to be treated, the notes core
 * never names the module. Mirrors App\Services\Tasks\TaskNotable.
 */
final class WorkOrderNotable implements EnlistsMentionedUsers, NotableEntity
{
    /** The "Commesse" viewing permission; a project commessa asks for its own (WorkOrderTypeAccess). */
    public const string VIEW_PERMISSION = 'work-orders.view';

    /** The detail tab holding the notes: a plain record page opens on Info (user directive 2026-11-16). */
    private const string NOTES_TAB_QUERY = '?tab=notes';

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
        /** @var WorkOrder $record */
        if (! $user->can(WorkOrderTypeAccess::viewPermission($record->type))) {
            return false;
        }

        return WorkOrderVisibilityScope::isVisibleTo($user, $record);
    }

    /**
     * The @ menu offers the commessa's team only: the readers who are
     * Responsabili or Partecipanti (spec 0134 D-2 rev. 2026-11-24, user
     * report). A reader outside the team (viewAll, viewSite, super-admin) is
     * mentioned through "Menziona chiunque" and joins the Partecipanti (spec 0353).
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        /** @var WorkOrder $record */
        return $this->readersQuery($record)->whereIn('id', $this->memberIds($record));
    }

    /**
     * authorizeRead() read from the other end: active users holding
     * `work-orders.view` (`project-work-orders.view` on a project commessa) who are Responsabili/Partecipanti or hold
     * `work-orders.viewAll`, plus (spec 0316) holders of `work-orders.viewSite`
     * belonging to an operational site of one of the commessa's source offers,
     * plus super-admins. The Task perimeter reuses it (spec 0379 D-8). Both
     * permission branches are probed for existence first because spatie's
     * `permission()` scope throws on a name no row carries (unsynced catalogue
     * must answer, not 500).
     */
    public function readersQuery(WorkOrder $record): Builder
    {
        $memberIds = $this->memberIds($record);
        $viewPermission = WorkOrderTypeAccess::viewPermission($record->type);
        $viewExists = $this->permissionExists($viewPermission);
        $viewAllExists = $this->permissionExists(WorkOrderVisibilityScope::VIEW_ALL_PERMISSION);
        $siteIds = $this->sourceSiteIds($record);
        $siteExists = $siteIds !== [] && $this->permissionExists(WorkOrderVisibilityScope::VIEW_SITE_PERMISSION);

        return User::query()
            ->excludingServiceAccounts()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($memberIds, $viewPermission, $viewExists, $viewAllExists, $siteExists, $siteIds): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->when($viewExists, function (Builder $canRead) use ($memberIds, $viewPermission, $viewAllExists, $siteExists, $siteIds): void {
                        $canRead->orWhere(function (Builder $reader) use ($memberIds, $viewPermission, $viewAllExists, $siteExists, $siteIds): void {
                            $reader->permission($viewPermission)
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
     * and a link that lands on a 403 is worse than no link at all. It opens
     * the Note tab, where the mention is (spec 0134 D-2 rev. 2026-11-23).
     */
    public function deepLinkPath(Model $record, User $recipient, ?int $quoteId): ?string
    {
        /** @var WorkOrder $record */
        return $this->authorizeRead($recipient, $record) ? WorkOrderTypeAccess::recordPath($record).self::NOTES_TAB_QUERY : null;
    }

    public function enlistmentRole(): string
    {
        return self::ROLE_PARTICIPANT;
    }

    /**
     * Whoever reads the thread may call anyone into it (spec 0353 D-2 rev.,
     * user directive 2026-11-23): the read gate, not the edit one.
     */
    public function canEnlist(User $actor, Model $record): bool
    {
        return $this->authorizeRead($actor, $record);
    }

    public function modulePermission(Model $record): string
    {
        /** @var WorkOrder $record */
        return WorkOrderTypeAccess::viewPermission($record->type);
    }

    /**
     * Newcomers take the first free Partecipanti slot (spec 0353, D-3/D-6);
     * all or nothing: a full team refuses the whole batch.
     */
    public function enlist(Model $record, array $userIds): array
    {
        /** @var WorkOrder $record */
        $current = ManagerPositions::positionsOf($record->participants()->get());
        $newcomers = array_values(array_diff($userIds, array_keys($current)));

        if ($newcomers === []) {
            return [];
        }

        $slots = ManagerPositions::slotsFromIds($current, [...array_keys($current), ...$newcomers]);

        if (count($slots) > ManagerPositions::MAX) {
            throw ValidationException::withMessages([
                'mentions' => [__("The work order's Partecipanti are full (max :max): :users cannot be added.", [
                    'max' => ManagerPositions::MAX,
                    'users' => User::query()->whereIn('id', $newcomers)->orderBy('name')->pluck('name')->implode(', '),
                ])],
            ]);
        }

        $before = PeopleChangeLog::capture($record);
        $attached = PositionalPivotSync::sync($record->participants(), ManagerPositions::syncMap($slots))['attached'];
        $record->unsetRelation('participants');
        PeopleChangeLog::log($before);

        return array_map('intval', $attached);
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
