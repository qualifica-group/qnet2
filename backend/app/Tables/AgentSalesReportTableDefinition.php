<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\Address;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\Quotes\QuoteVisibilityScope;
use App\Tables\AgentSalesReport\AgentSalesReportColumnCatalog;
use App\Tables\AgentSalesReport\AgentSalesReportRegistryColumns;
use App\Tables\Quotes\QuoteRelationColumns;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only `agent-sales-report` domain over `quotes` (spec 0365, D-4/D-5): the legacy "Report
 * Vendite Agenti". No row action, no bulk action, no editable cell. Rows are limited by
 * QuoteVisibilityScope (spec 0316), exactly like the Offerte grid. The relation columns
 * (`company_site`, `managers`, `quote_workflow_status`, `commercial`, `reporter`) reuse the
 * `quotes` domain's own filter/sort/distinct machinery; `registry` and `city` are the report's
 * own (AgentSalesReportRegistryColumns); `address`/`postal_code`/`phone`/`email` are projected
 * from the Registry card's primary address and primary contacts.
 */
class AgentSalesReportTableDefinition extends AbstractTableDefinition
{
    private const string VIEW_PERMISSION = 'agent-sales-report.view';

    public function __construct(
        private readonly QuoteRelationColumns $relationColumns,
        private readonly AgentSalesReportRegistryColumns $registryColumns,
        private readonly PrimaryContactColumn $contactColumn,
    ) {}

    public function domain(): string
    {
        return 'agent-sales-report';
    }

    /**
     * @return class-string<Quote>
     */
    public function modelClass(): string
    {
        return Quote::class;
    }

    public function authorizeViewAny(User $actor): bool
    {
        return Gate::forUser($actor)->allows(self::VIEW_PERMISSION);
    }

    /**
     * @return Builder<Quote>
     */
    public function baseQuery(): Builder
    {
        return QuoteVisibilityScope::scopeToActor(Quote::query(), Auth::user())->with([
            'opportunity.registry.personalData.addresses' => static fn ($query) => $query->where('is_primary', true),
            'opportunity.registry.personalData.addresses.city',
            'opportunity.registry.personalData.contacts' => static fn ($query) => $query->where('is_primary', true),
            'companySite', 'quoteWorkflowStatus', 'commercial', 'reporter', 'managers.avatar',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return AgentSalesReportColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return AgentSalesReportColumnCatalog::filters();
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
            ['columnId' => 'quote_date', 'direction' => 'desc'],
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
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Quote $row */
        $registry = $row->opportunity?->registry;
        /** @var Address|null $address */
        $address = $registry?->personalData?->addresses->first();
        $contacts = collect($this->contactColumn->formatFor($registry));

        return [
            'id' => $row->id,
            'code' => $row->code,
            'quote_date' => $row->quote_date?->toDateString(),
            'accepted_at' => $row->accepted_at?->toDateString(),
            'registry' => $registry?->name,
            'title' => $row->title,
            'address' => $address === null ? null : implode(', ', array_filter([$address->line1, $address->line2])),
            'postal_code' => $address?->postal_code,
            'city' => $address?->city?->name,
            'phone' => $contacts->firstWhere('type', 'phone')['value'] ?? null,
            'email' => $contacts->firstWhere('type', 'email')['value'] ?? null,
            'company_site' => $this->summarize($row->companySite),
            'managers' => $row->managers->map(fn (User $user): array => [
                'id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->avatarDataUri(),
            ])->all(),
            'quote_workflow_status' => $this->summarizeStatus($row->quoteWorkflowStatus),
            'revenue_net' => $row->revenue_net,
            'commercial' => $this->summarize($row->commercial),
            'reporter' => $this->summarize($row->reporter),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    /**
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->registryColumns->applyFilter($query, $columnId, $columnConfig, $filter)
            || $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<Quote>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->registryColumns->applySort($query, $columnId, $direction)
            || $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->registryColumns->distinctValues($columnId, $search, $query, $limit)
            ?? $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function summarizeStatus(?QuoteWorkflowStatus $status): ?array
    {
        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }
}
