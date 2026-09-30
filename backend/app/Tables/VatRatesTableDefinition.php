<?php

namespace App\Tables;

use App\Models\User;
use App\Models\VatRate;
use App\Tables\VatRates\VatRateColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `vat-rates` domain.
 *
 * All three columns (name, rate, created_at) are real DB columns handled
 * entirely by the generic engine — no derived column, mirroring
 * SourcesTableDefinition.
 */
class VatRatesTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'vat-rates';
    }

    /**
     * @return class-string<VatRate>
     */
    public function modelClass(): string
    {
        return VatRate::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives VatRatePolicy::viewAny from
    // modelClass() (vat-rates.viewAny).

    /**
     * @return Builder<VatRate>
     */
    public function baseQuery(): Builder
    {
        return VatRate::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return VatRateColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return VatRateColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return VatRateColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
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
     * Map a VatRate to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var VatRate $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'rate' => $row->rate,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via VatRatePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
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
}
