<?php

declare(strict_types=1);

namespace App\Tables;

use App\Http\Requests\PurchaseRequestLineStatuses\UpdatePurchaseRequestLineStatusRequest;
use App\Models\PurchaseRequestLineStatusLabel;
use App\Models\User;
use App\Services\PurchaseRequestLineStatusLabelService;
use App\Services\Table\FormRequestCellValidator;
use App\Tables\PurchaseRequestLineStatuses\PurchaseRequestLineStatusColumnCatalog as Catalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition of the `purchase-request-line-statuses` list (spec 0248):
 * real DB columns handled by the generic engine, read-only rows (no delete).
 */
class PurchaseRequestLineStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly PurchaseRequestLineStatusLabelService $service,
        private readonly FormRequestCellValidator $cells,
    ) {}

    /**
     * Spec 0258, D-1: the inline cell edit follows the catalogue's own update
     * FormRequest and service, never the generic `$row->update()`.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var PurchaseRequestLineStatusLabel $row */
        /** @var User $actor */
        $actor = Auth::user();
        $request = $this->cells->validate(UpdatePurchaseRequestLineStatusRequest::class, 'purchaseRequestLineStatus', $row, $actor, [$columnId => $value]);

        return $this->service->update($row, $request->toData());
    }

    public function domain(): string
    {
        return 'purchase-request-line-statuses';
    }

    /**
     * @return class-string<PurchaseRequestLineStatusLabel>
     */
    public function modelClass(): string
    {
        return PurchaseRequestLineStatusLabel::class;
    }

    /**
     * @return Builder<PurchaseRequestLineStatusLabel>
     */
    public function baseQuery(): Builder
    {
        return PurchaseRequestLineStatusLabel::query();
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
        return [['columnId' => 'sort_order', 'direction' => 'asc']];
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
        /** @var PurchaseRequestLineStatusLabel $row */
        return [
            'id' => $row->id,
            'system_key' => $row->system_key,
            'name' => $row->name,
            'color' => $row->color,
            'icon' => $row->icon,
            'sort_order' => $row->sort_order,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * No bulk/single delete exists for these rows (spec 0248, D-2): the generic
     * delete endpoint must never remove an enum case's presentation.
     */
    public function authorizeDelete(User $actor, Model $row): bool
    {
        return false;
    }
}
