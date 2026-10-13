<?php

namespace App\Tables;

use App\Authorization\AuthorizationRegistry;
use App\DataObjects\Enums\EnumMeta;
use App\Enums\CommissionAccrualType;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionAccrual;
use App\Models\User;
use App\Services\Table\RowGroupQuery;
use App\Tables\CommissionAccruals\CommissionAccrualColumnCatalog as Catalog;
use App\Tables\CommissionAccruals\CommissionAccrualDerivedQuery;
use App\Tables\CommissionAccruals\CommissionAccrualSql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `commission-accruals` domain (spec 0212): the
 * read-only ledger of the commission movements, readable flat or grouped
 * server-side (opt-in row grouping, sum of the amount per group).
 *
 * Everything is fetched with LEFT JOINs (no per-row queries). The amount
 * columns follow the `commission-accruals` field permissions: a column the
 * actor cannot see is removed from columns() itself, so config, sort/filter
 * allow-lists, export and the preferences all drop it together; mapRow(), the
 * groupable columns and the group aggregates drop it per actor. Nothing can be
 * edited or deleted: the ledger is written only by the synchronizer.
 */
class CommissionAccrualsTableDefinition extends AbstractTableDefinition
{
    private const string RESOURCE = 'commission-accruals';

    /** Columns summed per group, by the SQL of their value. */
    private const array AGGREGATES = ['amount'];

    public function __construct(
        private readonly AuthorizationRegistry $authorization,
        private readonly RowGroupQuery $rowGroups,
    ) {}

    public function domain(): string
    {
        return self::RESOURCE;
    }

    /**
     * @return class-string<CommissionAccrual>
     */
    public function modelClass(): string
    {
        return CommissionAccrual::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives CommissionAccrualPolicy::viewAny from modelClass().

    /**
     * @return Builder<CommissionAccrual>
     */
    public function baseQuery(): Builder
    {
        return CommissionAccrualSql::withJoins(CommissionAccrual::query())
            ->select('commission_accruals.*')
            ->selectRaw('quotes.id as quote_id, quotes.code as quote_code, products.name as product_name, registries.name as customer_name')
            ->selectRaw('invoices.number as invoice_number, invoices.year as invoice_year')
            ->selectRaw(CommissionAccrualSql::recipientName().' as recipient_name');
    }

    /**
     * The catalogue minus the columns whose field the current user cannot see.
     * The user comes from the auth guard (set by the request, and by the
     * export job): this method feeds the allow-lists, which take no actor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $actor = Auth::user();

        return array_values(array_filter(
            Catalog::columns(),
            fn (array $column): bool => ! $actor instanceof User || $this->columnVisible($actor, $column['id']),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filters($this->columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'accrued_on', 'direction' => 'desc'],
            ['columnId' => 'id', 'direction' => 'desc'],
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
     * Frontend `enums.*` keys: cells and Set Filter checklists read the same labels.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return match ($columnId) {
            'recipient_kind' => 'commission_recipient_kind',
            'recipient_role' => 'commission_recipient_role',
            'type' => 'commission_accrual_type',
            'status' => 'commission_accrual_status',
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        $options = match ($columnId) {
            'recipient_kind' => [
                new EnumMeta('referent', __('Referent'), 'blue'),
                new EnumMeta('user', __('User'), 'violet'),
            ],
            'recipient_role' => array_filter(
                CommissionRecipientRole::options(),
                static fn (EnumMeta $meta): bool => $meta->value !== CommissionRecipientRole::Supplier->value,
            ),
            'type' => CommissionAccrualType::options(),
            'status' => [
                new EnumMeta(CommissionAccrualSql::STATUS_OPEN, __('Open'), 'green'),
                new EnumMeta(CommissionAccrualSql::STATUS_SETTLED_EXTERNALLY, __('Settled externally'), 'slate'),
            ],
            default => null,
        };

        return $options === null ? null : array_values(array_map(static fn (EnumMeta $meta): array => $meta->toArray(), $options));
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var CommissionAccrual $row */
        $number = $row->getAttribute('invoice_number');
        $quoteId = $row->getAttribute('quote_id');

        $payload = [
            'id' => $row->id,
            'invoice_id' => $row->invoice_id,
            'quote_id' => $quoteId === null ? null : (int) $quoteId,
            'recipient_type' => $row->recipient_type,
            'recipient_id' => (int) $row->recipient_id,
            'accrued_on' => $row->accrued_on->toDateString(),
            'accrued_month' => $row->accrued_on->format('Y-m'),
            'recipient' => $row->getAttribute('recipient_name'),
            'recipient_kind' => $row->recipient_type,
            'recipient_role' => $row->recipient_role->value,
            'type' => $row->type->value,
            'invoice' => $number === null ? null : $number.'/'.$row->getAttribute('invoice_year'),
            'customer' => $row->getAttribute('customer_name'),
            'quote' => $row->getAttribute('quote_code'),
            'product' => $row->getAttribute('product_name'),
            'base_amount' => $row->base_amount,
            'amount' => $row->amount,
            'settled_externally_on' => $row->settled_externally_on?->toDateString(),
            'status' => $row->settled_externally_on === null ? CommissionAccrualSql::STATUS_OPEN : CommissionAccrualSql::STATUS_SETTLED_EXTERNALLY,
        ];

        foreach (Catalog::FIELD_BY_COLUMN as $column => $field) {
            if (! $this->fieldVisible($actor, $field)) {
                unset($payload[$column]);
            }
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return false;
    }

    public function authorizeDelete(User $actor, Model $row): bool
    {
        return false;
    }

    /**
     * Footer total over the whole filtered set, when the actor can see the amount.
     *
     * @param  Builder<CommissionAccrual>  $query
     * @return array<string, string>
     */
    public function aggregates(Builder $query): array
    {
        $actor = Auth::user();

        return $actor instanceof User ? $this->rowGroups->totals($this, $actor, $query) : [];
    }

    /**
     * @param  Builder<CommissionAccrual>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return CommissionAccrualDerivedQuery::applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<CommissionAccrual>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return CommissionAccrualDerivedQuery::applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<CommissionAccrual>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return CommissionAccrualDerivedQuery::distinctValues($columnId, $search, $query, $limit);
    }

    public function supportsRowGroups(): bool
    {
        return true;
    }

    /**
     * @return array<string, array{key: string, labels: array<int, string>}>
     */
    public function groupableColumns(User $actor): array
    {
        $role = CommissionAccrualSql::PLAIN_COLUMNS['recipient_role'];
        $type = CommissionAccrualSql::PLAIN_COLUMNS['type'];
        $month = CommissionAccrualSql::accruedMonth();

        return [
            'recipient' => ['key' => CommissionAccrualSql::recipientKey(), 'labels' => [CommissionAccrualSql::recipientName()]],
            'recipient_role' => ['key' => $role, 'labels' => [$role]],
            'accrued_month' => ['key' => $month, 'labels' => [$month]],
            'type' => ['key' => $type, 'labels' => [$type]],
            'customer' => ['key' => 'invoices.customer_registry_id', 'labels' => ['registries.name']],
        ];
    }

    /**
     * @return array<string, array{func: string, expression: string}>
     */
    public function groupAggregates(User $actor): array
    {
        $aggregates = [];

        foreach (self::AGGREGATES as $columnId) {
            if ($this->columnVisible($actor, $columnId)) {
                $aggregates[$columnId] = ['func' => 'sum', 'expression' => CommissionAccrualSql::PLAIN_COLUMNS[$columnId]];
            }
        }

        return $aggregates;
    }

    private function columnVisible(User $actor, string $columnId): bool
    {
        $field = Catalog::FIELD_BY_COLUMN[$columnId] ?? null;

        return $field === null || $this->fieldVisible($actor, $field);
    }

    private function fieldVisible(User $actor, string $field): bool
    {
        return $this->authorization
            ->resolve(self::RESOURCE)
            ->fieldPermissions($actor, new CommissionAccrual)[$field]
            ->visible;
    }
}
