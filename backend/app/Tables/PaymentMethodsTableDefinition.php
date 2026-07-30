<?php

namespace App\Tables;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\PaymentMethodService;
use App\Tables\PaymentMethods\PaymentMethodAdvancedFilterCatalog;
use App\Tables\PaymentMethods\PaymentMethodColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `payment-methods` domain (spec 0068).
 *
 * Every column (name, code, description, payment_days, sort_order,
 * is_active, created_at, updated_at) is a real DB column handled entirely by
 * the generic engine.
 */
class PaymentMethodsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly PaymentMethodService $service) {}

    public function domain(): string
    {
        return 'payment-methods';
    }

    /**
     * @return class-string<PaymentMethod>
     */
    public function modelClass(): string
    {
        return PaymentMethod::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives PaymentMethodPolicy::viewAny
    // from modelClass() (payment-methods.viewAny).

    /**
     * @return Builder<PaymentMethod>
     */
    public function baseQuery(): Builder
    {
        return PaymentMethod::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return PaymentMethodColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return PaymentMethodColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return PaymentMethodColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return PaymentMethodAdvancedFilterCatalog::advancedFilters();
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
     * Map a PaymentMethod to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var PaymentMethod $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'code' => $row->code,
            'description' => $row->description,
            'payment_instructions' => $row->payment_instructions,
            'payment_days' => $row->payment_days,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via PaymentMethodPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var PaymentMethod $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
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
     * Delegate to PaymentMethodService::delete() so the generic bulk-delete
     * endpoint respects the SAME behaviour as the single DELETE
     * /payment-methods/{paymentMethod} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var PaymentMethod $model */
        $this->service->delete($model);
    }
}
