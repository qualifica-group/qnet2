<?php

namespace App\Tables;

use App\Authorization\AuthorizationRegistry;
use App\Enums\InstallmentStatus;
use App\Models\InvoiceInstallment;
use App\Models\User;
use App\Services\Table\RowGroupQuery;
use App\Tables\InvoiceInstallments\InstallmentColumnCatalog;
use App\Tables\InvoiceInstallments\InstallmentDerivedQuery;
use App\Tables\InvoiceInstallments\InstallmentSql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `invoice-installments` domain (spec 0197): every
 * installment of the active invoices with the customer, work order and sites
 * of its invoice, readable flat or grouped server-side (opt-in row grouping).
 *
 * Everything is fetched with JOINs (no per-row queries). Amount and collection
 * columns follow the `invoice-installments` field permissions: a column the
 * actor cannot see is removed from columns() itself, so config, sort/filter
 * allow-lists, export and the preferences all drop it together; mapRow() and
 * the group aggregates drop it per actor.
 */
class InvoiceInstallmentsTableDefinition extends AbstractTableDefinition
{
    private const string RESOURCE = 'invoice-installments';

    /** Columns summed per group, by the SQL of their value. */
    private const array AGGREGATES = ['amount', 'collected_amount', 'residual_amount'];

    public function __construct(
        private readonly AuthorizationRegistry $authorization,
        private readonly RowGroupQuery $rowGroups,
    ) {}

    public function domain(): string
    {
        return self::RESOURCE;
    }

    /**
     * @return class-string<InvoiceInstallment>
     */
    public function modelClass(): string
    {
        return InvoiceInstallment::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives InvoiceInstallmentPolicy::viewAny from modelClass().

    /**
     * @return Builder<InvoiceInstallment>
     */
    public function baseQuery(): Builder
    {
        return InvoiceInstallment::query()
            ->select('invoice_installments.*')
            ->selectRaw('invoices.number as invoice_number, invoices.year as invoice_year, invoices.document_date as invoice_document_date')
            ->selectRaw('registries.name as customer_name, companies.denomination as company_name, company_sites.name as company_site_name')
            ->selectRaw('work_orders.code as work_order_code, work_orders.title as work_order_title')
            ->selectRaw(InstallmentSql::operationalSiteLabel().' as operational_site_label')
            ->join('invoices', 'invoices.id', '=', 'invoice_installments.invoice_id')
            ->join('registries', 'registries.id', '=', 'invoices.customer_registry_id')
            ->join('companies', 'companies.id', '=', 'invoices.company_id')
            ->leftJoin('work_orders', 'work_orders.id', '=', 'invoices.work_order_id')
            ->leftJoin('quotes', 'quotes.id', '=', 'invoices.quote_id')
            ->leftJoin('company_sites', 'company_sites.id', '=', 'quotes.company_site_id')
            ->leftJoin('operational_sites', 'operational_sites.id', '=', 'quotes.operational_site_id');
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
            InstallmentColumnCatalog::columns(),
            fn (array $column): bool => ! $actor instanceof User || $this->columnVisible($actor, $column['id']),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return InstallmentColumnCatalog::filters($this->columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return InstallmentColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'due_date', 'direction' => 'asc'],
            ['columnId' => 'invoice_number_label', 'direction' => 'asc'],
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
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'status'
            ? array_map(static fn ($meta): array => $meta->toArray(), InstallmentStatus::options())
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var InvoiceInstallment $row */
        $daysOverdue = $row->daysOverdue();
        $workOrder = $row->getAttribute('work_order_code');

        $payload = [
            'id' => $row->id,
            'invoice_id' => $row->invoice_id,
            'invoice_number_label' => $row->getAttribute('invoice_number').'/'.$row->getAttribute('invoice_year'),
            'invoice_document_date' => substr((string) $row->getAttribute('invoice_document_date'), 0, 10),
            'sequence' => $row->sequence,
            'due_date' => $row->due_date->toDateString(),
            'due_month' => $row->due_date->format('Y-m'),
            'days_overdue' => $daysOverdue,
            'status' => $row->status()->value,
            'overdue' => $daysOverdue > 0 ? 'yes' : 'no',
            'customer' => $row->getAttribute('customer_name'),
            'work_order' => $workOrder === null ? null : $workOrder.' - '.$row->getAttribute('work_order_title'),
            'company' => $row->getAttribute('company_name'),
            'company_site' => $row->getAttribute('company_site_name'),
            'operational_site' => $row->getAttribute('operational_site_label'),
            'payment_method_code' => $row->payment_method_code,
            'amount' => $row->amount,
            'collected_amount' => $row->collected_amount,
            'residual_amount' => $row->residualAmount(),
            'collected_at' => $row->collected_at?->toDateString(),
        ];

        foreach (InstallmentColumnCatalog::FIELD_BY_COLUMN as $column => $field) {
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
        /** @var InvoiceInstallment $row */
        $status = $row->status();
        $allowed = [];

        if ($actor->can('invoices.view')) {
            $allowed[] = 'view_invoice';
        }

        // An installment with a collection can no longer be edited (409).
        if ($status === InstallmentStatus::Unpaid && Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if ($actor->can('invoices.collect')) {
            if ($status !== InstallmentStatus::Paid) {
                $allowed[] = 'record_collection';
            }

            if ($status !== InstallmentStatus::Unpaid) {
                $allowed[] = 'clear_collection';
            }
        }

        return $allowed;
    }

    /**
     * Footer totals over the whole filtered set, limited to the amount columns
     * the current user can see.
     *
     * @param  Builder<InvoiceInstallment>  $query
     * @return array<string, string>
     */
    public function aggregates(Builder $query): array
    {
        $actor = Auth::user();

        return $actor instanceof User ? $this->rowGroups->totals($this, $actor, $query) : [];
    }

    /**
     * @param  Builder<InvoiceInstallment>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return InstallmentDerivedQuery::applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<InvoiceInstallment>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return InstallmentDerivedQuery::applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<InvoiceInstallment>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return InstallmentDerivedQuery::distinctValues($columnId, $search, $query, $limit);
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
        $paymentMethodCode = InstallmentSql::PLAIN_COLUMNS['payment_method_code'];
        $dueMonth = InstallmentSql::dueMonth();

        return [
            'customer' => ['key' => 'invoices.customer_registry_id', 'labels' => ['registries.name']],
            'work_order' => ['key' => 'invoices.work_order_id', 'labels' => ['work_orders.code', 'work_orders.title']],
            'company_site' => ['key' => 'quotes.company_site_id', 'labels' => ['company_sites.name']],
            'operational_site' => ['key' => 'quotes.operational_site_id', 'labels' => [InstallmentSql::operationalSiteLabel()]],
            'company' => ['key' => 'invoices.company_id', 'labels' => ['companies.denomination']],
            'payment_method_code' => ['key' => $paymentMethodCode, 'labels' => [$paymentMethodCode]],
            'due_month' => ['key' => $dueMonth, 'labels' => [$dueMonth]],
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
                $aggregates[$columnId] = ['func' => 'sum', 'expression' => InstallmentSql::PLAIN_COLUMNS[$columnId]];
            }
        }

        return $aggregates;
    }

    private function columnVisible(User $actor, string $columnId): bool
    {
        $field = InstallmentColumnCatalog::FIELD_BY_COLUMN[$columnId] ?? null;

        return $field === null || $this->fieldVisible($actor, $field);
    }

    private function fieldVisible(User $actor, string $field): bool
    {
        return $this->authorization
            ->resolve(self::RESOURCE)
            ->fieldPermissions($actor, new InvoiceInstallment)[$field]
            ->visible;
    }
}
