<?php

namespace App\Tables;

use App\Enums\PurchaseRequestCapability;
use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestPriority;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestCapabilityResolver;
use App\Services\PurchaseRequests\PurchaseRequestVisibilityScope;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use App\Tables\PurchaseRequests\PurchaseRequestLineColumnCatalog as Catalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `purchase-request-lines` domain (spec 0208): the
 * "Gestione righe" grid, one row per line. Visibility follows the parent
 * request (D-16). The row carries `abilities.transitions` so the mass status
 * change offers only what the actor may do; the change itself is the
 * `purchase-request-lines/status` endpoint. Rows are read-only.
 */
class PurchaseRequestLinesTableDefinition extends AbstractTableDefinition
{
    private const string PRIORITY_COLUMN = 'priority';

    public function __construct(private readonly PurchaseRequestCapabilityResolver $capabilities) {}

    public function domain(): string
    {
        return 'purchase-request-lines';
    }

    /**
     * @return class-string<PurchaseRequestLine>
     */
    public function modelClass(): string
    {
        return PurchaseRequestLine::class;
    }

    // authorizeViewAny() resolves through PurchaseRequestLine -> no policy would
    // be found, so it is explicit: the same `view` gate as the RDA list.
    public function authorizeViewAny(User $actor): bool
    {
        return Gate::forUser($actor)->allows('viewAny', PurchaseRequest::class);
    }

    /**
     * @return Builder<PurchaseRequestLine>
     */
    public function baseQuery(): Builder
    {
        $query = PurchaseRequestLine::query()->with([
            'purchaseRequest:id,subject,priority,requested_at,requester_id,function_manager_id,created_by,status',
            'purchaseRequest.requester:id,name',
            'purchaseRequest.functionManager:id,name',
            'unitOfMeasure:id,name,symbol',
            'approvedBy:id,name',
        ]);

        return PurchaseRequestVisibilityScope::scopeLinesToActor($query, Auth::user());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return Catalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return Catalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'purchase_request_id', 'direction' => 'desc'],
            ['columnId' => 'position', 'direction' => 'asc'],
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
        /** @var PurchaseRequestLine $row */
        $request = $row->purchaseRequest;

        return [
            'id' => $row->id,
            'purchase_request_id' => $row->purchase_request_id,
            'purchase_request_subject' => $request->subject,
            'priority' => $request->priority->value,
            'requester' => ['id' => $request->requester->id, 'name' => $request->requester->name],
            'function_manager' => ['id' => $request->functionManager->id, 'name' => $request->functionManager->name],
            'requested_at' => $request->requested_at->toDateString(),
            'position' => $row->position,
            'description' => $row->description,
            'unit_of_measure' => $row->unitOfMeasure === null ? null : ['id' => $row->unitOfMeasure->id, 'name' => $row->unitOfMeasure->name, 'symbol' => $row->unitOfMeasure->symbol],
            'quantity' => $row->quantity,
            'unit_price' => $row->unit_price,
            'taxable_amount' => $row->taxable_amount,
            'vat_amount' => $row->vat_amount,
            'total_amount' => $row->total_amount,
            'status' => $row->status->value,
            'approved_by' => $row->approvedBy === null ? null : ['id' => $row->approvedBy->id, 'name' => $row->approvedBy->name],
            'approved_at' => $row->approved_at,
            'oda_reference' => $row->oda_reference,
            'abilities' => [
                'transitions' => array_map(
                    static fn (PurchaseRequestLineStatus $status): string => $status->value,
                    $this->capabilities->transitions($actor, $request, $row),
                ),
                'capabilities' => array_map(
                    static fn (PurchaseRequestCapability $capability): string => $capability->value,
                    $this->capabilities->effectiveCapabilities($actor, $request),
                ),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return ['view', 'history'];
    }

    /**
     * @param  Builder<PurchaseRequestLine>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::PRIORITY_COLUMN) {
            Derived::applySet($query, 'purchaseRequest', 'priority', PurchaseRequestPriority::values(), $filter);

            return true;
        }

        if (! isset(Catalog::TEXT_RELATIONS[$columnId])) {
            return false;
        }

        Derived::applyText($query, Catalog::TEXT_RELATIONS[$columnId], $filter);

        return true;
    }

    /**
     * @param  Builder<PurchaseRequestLine>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (! isset(Catalog::PARENT_SORTS[$columnId])) {
            return false;
        }

        Derived::orderByRelated($query, 'purchase_requests', 'purchase_request_lines.purchase_request_id', Catalog::PARENT_SORTS[$columnId], $direction);

        return true;
    }

    /**
     * @param  Builder<PurchaseRequestLine>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match (true) {
            $columnId === self::PRIORITY_COLUMN => PurchaseRequestPriority::values(),
            $columnId === 'status' => PurchaseRequestLineStatus::values(),
            isset(Catalog::TEXT_RELATIONS[$columnId]) => Derived::distinctRelated($query, Catalog::TEXT_RELATIONS[$columnId], $search, $limit),
            default => null,
        };
    }
}
