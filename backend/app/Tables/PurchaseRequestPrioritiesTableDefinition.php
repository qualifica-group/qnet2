<?php

declare(strict_types=1);

namespace App\Tables;

use App\Http\Requests\PurchaseRequestPriorities\UpdatePurchaseRequestPriorityRequest;
use App\Models\PurchaseRequestPriority;
use App\Models\User;
use App\Services\PurchaseRequestPriorityService;
use App\Services\Table\FormRequestCellValidator;
use App\Tables\PurchaseRequestPriorities\PurchaseRequestPriorityColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `purchase-request-priorities` domain (spec 0101, D-4).
 *
 * Every column (name, description, color, icon, sort_order, is_active,
 * created_at) is a real DB column handled entirely by the generic engine.
 */
class PurchaseRequestPrioritiesTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly PurchaseRequestPriorityService $service,
        private readonly FormRequestCellValidator $cells,
    ) {}

    /**
     * Spec 0258, D-1: the inline cell edit follows the catalogue's own update
     * FormRequest and service, never the generic `$row->update()`.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var PurchaseRequestPriority $row */
        /** @var User $actor */
        $actor = Auth::user();
        $request = $this->cells->validate(UpdatePurchaseRequestPriorityRequest::class, 'purchaseRequestPriority', $row, $actor, [$columnId => $value]);

        return $this->service->update($row, $request->toData());
    }

    public function domain(): string
    {
        return 'purchase-request-priorities';
    }

    /**
     * @return class-string<PurchaseRequestPriority>
     */
    public function modelClass(): string
    {
        return PurchaseRequestPriority::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives PurchaseRequestPriorityPolicy::viewAny
    // from modelClass() (purchase-request-priorities.viewAny).

    /**
     * @return Builder<PurchaseRequestPriority>
     */
    public function baseQuery(): Builder
    {
        return PurchaseRequestPriority::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return PurchaseRequestPriorityColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return PurchaseRequestPriorityColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return PurchaseRequestPriorityColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
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
     * Map a PurchaseRequestPriority to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var PurchaseRequestPriority $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'color' => $row->color,
            'icon' => $row->icon,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'is_default' => $row->is_default,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via PurchaseRequestPriorityPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var PurchaseRequestPriority $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to PurchaseRequestPriorityService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (the referenced-by-a-purchase-request guard, D-8b) as the single
     * DELETE /purchase-request-priorities/{purchaseRequestPriority} endpoint (AC-041).
     */
    public function deleteModel(Model $model): void
    {
        /** @var PurchaseRequestPriority $model */
        $this->service->delete($model);
    }
}
