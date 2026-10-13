<?php

namespace App\Tables;

use App\Authorization\AuthorizationRegistry;
use App\Enums\PurchaseInstallmentStatus;
use App\Enums\PurchaseInvoiceType;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use App\Services\Table\RowGroupQuery;
use App\Tables\PurchaseInvoiceInstallments\PurchaseInstallmentColumnCatalog;
use App\Tables\PurchaseInvoiceInstallments\PurchaseInstallmentDerivedQuery;
use App\Tables\PurchaseInvoiceInstallments\PurchaseInstallmentSql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `purchase-invoice-installments` domain (spec 0211):
 * every installment of the payable documents with the supplier, work order and
 * sites of its document, readable flat or grouped server-side (opt-in row
 * grouping). Mirror of the active `invoice-installments` domain (spec 0197).
 *
 * Everything is fetched with JOINs (no per-row queries). Amount, payment and
 * account columns follow the `purchase-invoice-installments` field permissions:
 * a column the actor cannot see is removed from columns() itself, so config,
 * sort/filter allow-lists, export and the preferences all drop it together;
 * mapRow(), the groupable columns and the group aggregates drop it per actor.
 */
class PurchaseInvoiceInstallmentsTableDefinition extends AbstractTableDefinition
{
    private const string RESOURCE = 'purchase-invoice-installments';

    /** Columns summed per group, by the SQL of their value. */
    private const array AGGREGATES = ['amount', 'paid_amount', 'residual_amount'];

    public function __construct(
        private readonly AuthorizationRegistry $authorization,
        private readonly RowGroupQuery $rowGroups,
    ) {}

    public function domain(): string
    {
        return self::RESOURCE;
    }

    /**
     * @return class-string<PurchaseInvoiceInstallment>
     */
    public function modelClass(): string
    {
        return PurchaseInvoiceInstallment::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives PurchaseInvoiceInstallmentPolicy::viewAny from modelClass().

    /**
     * @return Builder<PurchaseInvoiceInstallment>
     */
    public function baseQuery(): Builder
    {
        return PurchaseInvoiceInstallment::query()
            ->select('purchase_invoice_installments.*')
            ->selectRaw('purchase_invoices.supplier_id as supplier_id, purchase_invoices.document_type as doc_type, purchase_invoices.document_number as doc_number, purchase_invoices.document_date as doc_date')
            ->selectRaw('registries.name as supplier_name, cost_types.name as cost_type_name, cost_types.color as cost_type_color, companies.denomination as company_name, company_sites.name as company_site_name, financial_accounts.name as financial_account_name')
            ->selectRaw('work_orders.code as work_order_code, work_orders.title as work_order_title')
            ->selectRaw(PurchaseInstallmentSql::operationalSiteLabel().' as operational_site_label')
            ->selectRaw(PurchaseInstallmentSql::purchaseOrderCodes('purchase_invoices.id').' as purchase_orders_label')
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_installments.purchase_invoice_id')
            ->join('registries', 'registries.id', '=', 'purchase_invoices.supplier_id')
            ->join('companies', 'companies.id', '=', 'purchase_invoices.company_id')
            ->leftJoin('cost_types', 'cost_types.id', '=', 'purchase_invoices.cost_type_id')
            ->leftJoin('work_orders', 'work_orders.id', '=', 'purchase_invoices.work_order_id')
            ->leftJoin('company_sites', 'company_sites.id', '=', 'purchase_invoices.company_site_id')
            ->leftJoin('operational_sites', 'operational_sites.id', '=', 'purchase_invoices.operational_site_id')
            ->leftJoin('financial_accounts', 'financial_accounts.id', '=', 'purchase_invoice_installments.financial_account_id');
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
            PurchaseInstallmentColumnCatalog::columns(),
            fn (array $column): bool => ! $actor instanceof User || $this->columnVisible($actor, $column['id']),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return PurchaseInstallmentColumnCatalog::filters($this->columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return PurchaseInstallmentColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'due_date', 'direction' => 'asc'],
            ['columnId' => 'document_date', 'direction' => 'asc'],
            ['columnId' => 'sequence', 'direction' => 'asc'],
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
            'status' => 'purchase_installment_status',
            'overdue' => 'installment_overdue',
            'document_type' => 'purchase_invoice_type',
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        $options = match ($columnId) {
            'status' => PurchaseInstallmentStatus::options(),
            'document_type' => PurchaseInvoiceType::options(),
            default => null,
        };

        return $options === null ? null : array_map(static fn ($meta): array => $meta->toArray(), $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var PurchaseInvoiceInstallment $row */
        $daysOverdue = $row->daysOverdue();
        $workOrder = $row->getAttribute('work_order_code');

        $payload = [
            'id' => $row->id,
            'purchase_invoice_id' => $row->purchase_invoice_id,
            'supplier_id' => (int) $row->getAttribute('supplier_id'),
            'document_type' => $row->getAttribute('doc_type'),
            'document_number' => $row->getAttribute('doc_number'),
            'document_date' => substr((string) $row->getAttribute('doc_date'), 0, 10),
            'purchase_orders' => $row->getAttribute('purchase_orders_label'),
            'sequence' => $row->sequence,
            'due_date' => $row->due_date->toDateString(),
            'due_month' => $row->due_date->format('Y-m'),
            'days_overdue' => $daysOverdue,
            'status' => $row->status()->value,
            'overdue' => $daysOverdue > 0 ? 'yes' : 'no',
            'supplier' => $row->getAttribute('supplier_name'),
            'cost_type' => $this->costTypeBadge($row),
            'work_order' => $workOrder === null ? null : $workOrder.' - '.$row->getAttribute('work_order_title'),
            'company' => $row->getAttribute('company_name'),
            'company_site' => $row->getAttribute('company_site_name'),
            'operational_site' => $row->getAttribute('operational_site_label'),
            'payment_method_code' => $row->payment_method_code,
            'amount' => $row->amount,
            'paid_amount' => $row->paid_amount,
            'residual_amount' => $row->residualAmount(),
            'paid_at' => $row->paid_at?->toDateString(),
            'financial_account' => $row->getAttribute('financial_account_name'),
        ];

        foreach (PurchaseInstallmentColumnCatalog::FIELD_BY_COLUMN as $column => $field) {
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
        /** @var PurchaseInvoiceInstallment $row */
        $paid = $row->isPaid();
        $allowed = [];

        // A paid installment can no longer be edited (409).
        if (! $paid && Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if ($actor->can('purchase-invoices.pay')) {
            $allowed[] = $paid ? 'clear_payment' : 'record_payment';
        }

        if ($actor->can('purchase-invoices.view')) {
            $allowed[] = 'open_document';
        }

        return $allowed;
    }

    /**
     * Footer totals over the whole filtered set, limited to the amount columns
     * the current user can see.
     *
     * @param  Builder<PurchaseInvoiceInstallment>  $query
     * @return array<string, string>
     */
    public function aggregates(Builder $query): array
    {
        $actor = Auth::user();

        return $actor instanceof User ? $this->rowGroups->totals($this, $actor, $query) : [];
    }

    /**
     * @param  Builder<PurchaseInvoiceInstallment>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return PurchaseInstallmentDerivedQuery::applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<PurchaseInvoiceInstallment>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return PurchaseInstallmentDerivedQuery::applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<PurchaseInvoiceInstallment>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return PurchaseInstallmentDerivedQuery::distinctValues($columnId, $search, $query, $limit);
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
        $paymentMethodCode = PurchaseInstallmentSql::PLAIN_COLUMNS['payment_method_code'];
        $dueMonth = PurchaseInstallmentSql::dueMonth();

        $columns = [
            'supplier' => ['key' => 'purchase_invoices.supplier_id', 'labels' => ['registries.name']],
            'cost_type' => ['key' => 'purchase_invoices.cost_type_id', 'labels' => ['cost_types.name']],
            'work_order' => ['key' => 'purchase_invoices.work_order_id', 'labels' => ['work_orders.code', 'work_orders.title']],
            'company' => ['key' => 'purchase_invoices.company_id', 'labels' => ['companies.denomination']],
            'company_site' => ['key' => 'purchase_invoices.company_site_id', 'labels' => ['company_sites.name']],
            'operational_site' => ['key' => 'purchase_invoices.operational_site_id', 'labels' => [PurchaseInstallmentSql::operationalSiteLabel()]],
            'payment_method_code' => ['key' => $paymentMethodCode, 'labels' => [$paymentMethodCode]],
            'financial_account' => ['key' => 'purchase_invoice_installments.financial_account_id', 'labels' => ['financial_accounts.name']],
            'due_month' => ['key' => $dueMonth, 'labels' => [$dueMonth]],
        ];

        // A grouped column the actor cannot see would leak it through the group labels.
        return array_filter($columns, fn (string $columnId): bool => $this->columnVisible($actor, $columnId), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array<string, array{func: string, expression: string}>
     */
    public function groupAggregates(User $actor): array
    {
        $aggregates = [];

        foreach (self::AGGREGATES as $columnId) {
            if ($this->columnVisible($actor, $columnId)) {
                $aggregates[$columnId] = ['func' => 'sum', 'expression' => PurchaseInstallmentSql::PLAIN_COLUMNS[$columnId]];
            }
        }

        return $aggregates;
    }

    private function columnVisible(User $actor, string $columnId): bool
    {
        $field = PurchaseInstallmentColumnCatalog::FIELD_BY_COLUMN[$columnId] ?? null;

        return $field === null || $this->fieldVisible($actor, $field);
    }

    /**
     * The `cost_type` badge cell `{name, color}` (spec 0249, D-6), read from the
     * joined columns: no query per row. Null when the invoice has no cost type.
     *
     * @return array{name: string, color: string}|null
     */
    private function costTypeBadge(Model $row): ?array
    {
        $name = $row->getAttribute('cost_type_name');

        return $name === null ? null : ['name' => $name, 'color' => $row->getAttribute('cost_type_color')];
    }

    private function fieldVisible(User $actor, string $field): bool
    {
        return $this->authorization
            ->resolve(self::RESOURCE)
            ->fieldPermissions($actor, new PurchaseInvoiceInstallment)[$field]
            ->visible;
    }
}
