<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\InvoiceTag;
use App\Models\User;
use App\Services\InvoiceTagService;
use App\Tables\InvoiceTags\InvoiceTagColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `invoice-tags` domain (spec 0247, D-5). Every column
 * is a real DB column handled entirely by the generic engine.
 */
class InvoiceTagsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly InvoiceTagService $service) {}

    public function domain(): string
    {
        return 'invoice-tags';
    }

    /**
     * @return class-string<InvoiceTag>
     */
    public function modelClass(): string
    {
        return InvoiceTag::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives InvoiceTagPolicy::viewAny from modelClass().

    /**
     * @return Builder<InvoiceTag>
     */
    public function baseQuery(): Builder
    {
        return InvoiceTag::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return InvoiceTagColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return InvoiceTagColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return InvoiceTagColumnCatalog::actions();
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
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var InvoiceTag $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var InvoiceTag $row */
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
     * Delegate to InvoiceTagService::delete() so the generic bulk-delete
     * respects the same in-use guard as DELETE /invoice-tags/{invoiceTag}.
     */
    public function deleteModel(Model $model): void
    {
        /** @var InvoiceTag $model */
        $this->service->delete($model);
    }
}
