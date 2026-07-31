<?php

declare(strict_types=1);

namespace App\Tables;

use App\Enums\ContractStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use App\Services\Contracts\ContractAlertResolver;
use App\Tables\Contracts\ContractAdvancedFilterCatalog;
use App\Tables\Contracts\ContractColumnCatalog;
use App\Tables\Contracts\ContractRelationColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `contracts` domain (spec 0072, MT-04).
 *
 * D-1: `contracts` owns almost none of the data it shows — `code`/`title`/
 * `registry`/`opportunity`/`commercial`/`reporter`/`supervisor`/`quote_date`/
 * `revenue_net`/`revenue_vat` are all projected live off the eager-loaded
 * `quote` tree (delegated to ContractRelationColumns, file-size split,
 * engineering.md §6). `contract_status` is the one own-FK relation.
 * `accepted_at`/`validated_at`/`renewal_date`/`expiry_date`/`terminated_at`
 * are real `contracts` columns, handled entirely by the generic engine.
 *
 * `revenue_gross` (net + vat, D-9-style, never persisted) is specially
 * derived here: sortable via a constant correlated-subquery expression
 * (backend.md §8's sole permitted raw escape hatch — zero input
 * interpolation), deliberately not filterable (see ContractColumnCatalog).
 *
 * `alert` (BR-6/D-4) is specially derived here too: computed per-row by the
 * shared ContractAlertResolver (never re-derived, so this table and
 * ContractResource/MT-02 can never disagree) and `set`-filterable via
 * date-window WHERE clauses built from `config('contracts.*')` — never
 * hardcoded, never raw SQL.
 */
class ContractsTableDefinition extends AbstractTableDefinition
{
    private const string ALERT_COLUMN = 'alert';

    private const string REVENUE_GROSS_COLUMN = 'revenue_gross';

    /** Mirrors ContractAlertResolver's own 2 return values exactly (BR-6). */
    private const string ALERT_EXPIRING = 'expiring';

    private const string ALERT_RENEWAL_DUE = 'renewal_due';

    public function __construct(
        private readonly ContractRelationColumns $relationColumns,
        private readonly ContractAlertResolver $alertResolver,
    ) {}

    public function domain(): string
    {
        return 'contracts';
    }

    /**
     * @return class-string<Contract>
     */
    public function modelClass(): string
    {
        return Contract::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ContractPolicy::viewAny from
    // modelClass() (contracts.viewAny).

    /**
     * @return Builder<Contract>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches to avoid N+1 across the
        // page: the whole `quote` tree this table projects (D-1), plus its
        // own `contractStatus` (contract_status_id is a real FK on
        // `contracts` itself).
        return Contract::query()->with([
            'quote.opportunity.registry',
            'quote.commercial',
            'quote.reporter',
            'quote.supervisor.avatar',
            'contractStatus',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ContractColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ContractColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ContractColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return ContractAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * `quote_date` (`quotes.created_at`) desc: mirrors QuotesTableDefinition's
     * own default (newest quote first) — the contract's lifecycle starts from
     * its quote, so "most recently quoted" is the natural default ordering,
     * not `contracts.created_at` (the automation-write timestamp, which
     * carries no business meaning for the user).
     *
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'quote_date', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map a Contract to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Contract $row */
        $quote = $row->quote;

        return [
            'id' => $row->id,
            'code' => $quote?->code,
            'title' => $quote?->title,
            'registry' => $this->summarize($quote?->opportunity?->registry),
            'opportunity' => $this->summarize($quote?->opportunity),
            'commercial' => $this->summarize($quote?->commercial),
            'reporter' => $this->summarize($quote?->reporter),
            'supervisor' => $this->userSummary($quote?->supervisor),
            'contract_status' => $this->summarizeContractStatus($row->contractStatus),
            'quote_date' => $quote?->created_at,
            'accepted_at' => $row->accepted_at,
            'validated_at' => $row->validated_at,
            'renewal_date' => $row->renewal_date,
            'expiry_date' => $row->expiry_date,
            'terminated_at' => $row->terminated_at,
            'revenue_net' => $quote?->revenue_net,
            'revenue_vat' => $quote?->revenue_vat,
            'revenue_gross' => $this->grossOf($quote?->revenue_net, $quote?->revenue_vat),
            'alert' => $this->alertResolver->resolve($row),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * The contract status projected WITH its `color` token, so the grid
     * renders the colored status badge; the generic summarize() would drop
     * it — mirrors QuotesTableDefinition::summarizeQuoteStatus().
     *
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function summarizeContractStatus(?ContractStatus $status): ?array
    {
        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }

    /**
     * A person summary carrying the inline avatar (data URI), mirroring
     * QuotesTableDefinition::userSummary(). Null when unset.
     *
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }

    /**
     * `revenue_gross` is derived (net + vat, D-9-style), never persisted —
     * same formula/formatting as ContractResource::grossOf().
     */
    private function grossOf(?string $net, ?string $vat): ?string
    {
        if ($net === null || $vat === null) {
            return null;
        }

        return number_format((float) $net + (float) $vat, 2, '.', '');
    }

    /**
     * Allowed action keys for a single row, via ContractPolicy. No
     * `create`/`delete` action exists (D-6, BR-8): `edit` is offered only
     * when `contracts.update` allows it (renewal/expiry/payment
     * notes/comments — the only PATCH-able fields), and the 4 domain actions
     * plus `change_status` are each gated on their own ability.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('validate', $row)) {
            $allowed[] = 'validate';
        }

        if (Gate::forUser($actor)->allows('schedule', $row)) {
            $allowed[] = 'schedule';
        }

        if (Gate::forUser($actor)->allows('changeStatus', $row)) {
            $allowed[] = 'change_status';
        }

        if (Gate::forUser($actor)->allows('terminate', $row)) {
            $allowed[] = 'terminate';
        }

        if (Gate::forUser($actor)->allows('reactivate', $row)) {
            $allowed[] = 'reactivate';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * D-6: a contract is never deleted by hand. `ContractPolicy` (BR-8)
     * deliberately exposes no `delete` ability, so `authorizeDelete()`
     * (Gate::allows('delete', ...)) already denies every actor — this
     * override is pure defence in depth so the generic bulk-delete endpoint
     * can never reach a live `$model->delete()` call even if that Gate check
     * were ever bypassed upstream.
     */
    public function deleteModel(Model $model): void
    {
        abort(403);
    }

    /**
     * `alert` is specially derived here (calculated at read time, BR-6);
     * every other derived column is delegated to ContractRelationColumns.
     *
     * @param  Builder<Contract>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::ALERT_COLUMN) {
            $this->applyAlertFilter($query, $filter);

            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * `revenue_gross` is specially derived here (a constant, hand-written
     * correlated-subquery expression — see revenueGrossSubquery()); every
     * other derived sort is delegated to ContractRelationColumns.
     *
     * @param  Builder<Contract>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::REVENUE_GROSS_COLUMN) {
            $query->orderBy($this->revenueGrossSubquery(), $direction);

            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `alert`'s 2 possible
     * values are a static enumeration (no query needed); every other derived
     * column is delegated to ContractRelationColumns.
     *
     * @param  Builder<Contract>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::ALERT_COLUMN) {
            return $this->alertDistinctValues($search);
        }

        return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }

    /**
     * Derived quick-search (spec 0009): `code`/`title` (the quote's own
     * columns), delegated to ContractRelationColumns.
     *
     * @param  Builder<Contract>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->relationColumns->applySearch($query, $columnId, $pattern);
    }

    /**
     * @return array<int, string>
     */
    private function alertDistinctValues(?string $search): array
    {
        $options = [self::ALERT_EXPIRING, self::ALERT_RENEWAL_DUE];

        if ($search === null || $search === '') {
            return $options;
        }

        return array_values(array_filter($options, static fn (string $option): bool => stripos($option, $search) !== false));
    }

    /**
     * `alert`'s `set` filter (AC-032): each requested value narrows to the
     * date window BR-6 defines, from `config('contracts.*')` — never
     * hardcoded. A `closed_lost` contract never matches either window
     * (mirrors ContractAlertResolver::isClosedLost() exactly, so the filter
     * and the calculated badge can never disagree).
     *
     * @param  Builder<Contract>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyAlertFilter(Builder $query, array $filter): void
    {
        $values = $this->alertFilterValues($filter);

        if ($values === []) {
            return;
        }

        $today = Carbon::today();
        $expiringUntil = $today->copy()->addDays((int) config('contracts.expiring_within_days'));
        $renewalUntil = $today->copy()->addDays((int) config('contracts.renewal_within_days'));

        $query->where(function (Builder $group) use ($values, $today, $expiringUntil, $renewalUntil): void {
            foreach ($values as $value) {
                match ($value) {
                    self::ALERT_EXPIRING => $group->orWhere(fn (Builder $q) => $this->constrainExpiring($q, $today, $expiringUntil)),
                    self::ALERT_RENEWAL_DUE => $group->orWhere(fn (Builder $q) => $this->constrainRenewalDue($q, $today, $expiringUntil, $renewalUntil)),
                    default => null, // not one of the 2 allow-listed values — ignored
                };
            }
        });
    }

    /**
     * @param  Builder<Contract>  $query
     */
    private function constrainExpiring(Builder $query, Carbon $today, Carbon $expiringUntil): void
    {
        $query->whereBetween('expiry_date', [$today, $expiringUntil])
            ->whereHas('contractStatus', static fn (Builder $status) => $status->where('group', '!=', ContractStatusGroup::ClosedLost));
    }

    /**
     * The expiry window takes precedence over the renewal window (BR-6):
     * a row already matching `expiring` must not ALSO match `renewal_due`.
     *
     * @param  Builder<Contract>  $query
     */
    private function constrainRenewalDue(Builder $query, Carbon $today, Carbon $expiringUntil, Carbon $renewalUntil): void
    {
        $query->whereBetween('renewal_date', [$today, $renewalUntil])
            ->where(function (Builder $notExpiring) use ($today, $expiringUntil): void {
                $notExpiring->whereNull('expiry_date')->orWhereNotBetween('expiry_date', [$today, $expiringUntil]);
            })
            ->whereHas('contractStatus', static fn (Builder $status) => $status->where('group', '!=', ContractStatusGroup::ClosedLost));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function alertFilterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            $values,
            static fn ($value): bool => in_array($value, [self::ALERT_EXPIRING, self::ALERT_RENEWAL_DUE], true),
        ));
    }

    /**
     * Constant, hand-written correlated-subquery expression — zero input
     * interpolation (backend.md §8 / security.md §8's sole permitted raw
     * escape hatch). `revenue_gross` is computed (net + vat, D-9-style),
     * never a real column: this is the only safe way to ORDER BY it without
     * pulling every row into PHP. Deliberately not filterable (see
     * ContractColumnCatalog) — a WHERE on a computed sum would need the same
     * arithmetic inside a filter predicate for a feature no AC requires.
     */
    private function revenueGrossSubquery(): QueryBuilder
    {
        return DB::table('quotes')
            ->select(DB::raw('(revenue_net + revenue_vat) as revenue_gross'))
            ->whereColumn('quotes.id', 'contracts.quote_id')
            ->limit(1);
    }
}
