<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Tables\FieldChangeRequests\FieldChangeRequestColumnCatalog;
use App\Tables\FieldChangeRequests\FieldChangeRequestRowMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `field-change-requests` domain (spec 0078): the
 * dedicated browse view over every FieldChangeRequest row (any resource/
 * field/status) — the AC-036 counterpart of the create/approve/reject
 * endpoints (a different microtask). Read-only by design: no editable
 * column and a single non-mutating row action (`view`, which opens the
 * detail carrying the Approve/Reject buttons) — a request is immutable once
 * handled (D-4), and approve/reject happen through their own dedicated
 * endpoints, never through this generic engine's updateCell()/deleteModel().
 *
 * authorizeViewAny() is intentionally NOT overridden: the fail-safe default
 * in AbstractTableDefinition derives FieldChangeRequestPolicy::viewAny from
 * modelClass() (field-change-requests.viewAny) — that Policy is owned by a
 * different microtask; if it is not yet on the filesystem, every request
 * here 403s (fail-closed), never fail-open.
 */
class FieldChangeRequestsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly FieldChangeRequestRowMapper $rowMapper) {}

    public function domain(): string
    {
        return 'field-change-requests';
    }

    /**
     * @return class-string<FieldChangeRequest>
     */
    public function modelClass(): string
    {
        return FieldChangeRequest::class;
    }

    /**
     * @return Builder<FieldChangeRequest>
     */
    public function baseQuery(): Builder
    {
        return FieldChangeRequest::query()->with(['requestedBy', 'handledBy', 'subject']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return FieldChangeRequestColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return FieldChangeRequestColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return FieldChangeRequestColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'status' ? FieldChangeRequestColumnCatalog::statusBadges() : null;
    }

    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'status' ? 'field_change_request_status' : null;
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
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var FieldChangeRequest $row */
        return $this->rowMapper->map($row);
    }

    /**
     * `view` only, via FieldChangeRequestPolicy: it also covers the requester
     * reading their OWN request without `field-change-requests.view`
     * (AC-039). No write action is ever advertised here (see actions()).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return Gate::forUser($actor)->allows('view', $row) ? ['view'] : [];
    }
}
