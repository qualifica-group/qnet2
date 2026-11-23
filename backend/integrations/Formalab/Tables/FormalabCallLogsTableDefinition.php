<?php

declare(strict_types=1);

namespace Integrations\Formalab\Tables;

use App\Models\User;
use App\Services\UserService;
use App\Tables\AbstractTableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Integrations\Formalab\Models\FormalabCallLog;

/**
 * The `formalab-call-logs` table domain: every call to or from Formalab.
 * Super-admin only, like the status page. Sort and filter run on the fixed
 * column allow-list below.
 */
class FormalabCallLogsTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'formalab:logs.columns';

    public function domain(): string
    {
        return 'formalab-call-logs';
    }

    /**
     * @return class-string<FormalabCallLog>
     */
    public function modelClass(): string
    {
        return FormalabCallLog::class;
    }

    public function authorizeViewAny(User $actor): bool
    {
        return $actor->hasRole(UserService::PRIVILEGED_ROLE);
    }

    /** The logs are an audit trail: never edited from the grid. */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return false;
    }

    /** The logs are an audit trail: removed only by the retention prune. */
    public function authorizeDelete(User $actor, Model $row): bool
    {
        return false;
    }

    /**
     * @return Builder<FormalabCallLog>
     */
    public function baseQuery(): Builder
    {
        return FormalabCallLog::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            $this->column('id', 'number', visible: false, sortable: true),
            $this->column('created_at', 'datetime', sortable: true, filter: 'date'),
            $this->column('direction', 'text', sortable: true, filter: 'set', options: [FormalabCallLog::DIRECTION_INBOUND, FormalabCallLog::DIRECTION_OUTBOUND]),
            $this->column('method', 'text'),
            $this->column('endpoint', 'text', sortable: true, searchable: true),
            $this->column('status_code', 'number', sortable: true),
            $this->column('outcome', 'text', sortable: true, filter: 'set', options: [FormalabCallLog::OUTCOME_SUCCESS, FormalabCallLog::OUTCOME_FAILURE]),
            $this->column('duration_ms', 'number', sortable: true),
            $this->column('trigger', 'text', sortable: true),
            $this->column('error_message', 'text'),
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private function column(string $id, string $type, bool $visible = true, bool $sortable = false, ?string $filter = null, ?array $options = null, bool $searchable = false): array
    {
        $column = [
            'id' => $id,
            'label' => self::LABELS.".{$id}",
            'type' => $type,
            'visible' => $visible,
            'sortable' => $sortable,
            'filterable' => $filter !== null,
            'filterType' => $filter,
        ];

        if ($options !== null) {
            $column['options'] = $options;
        }

        if ($searchable) {
            $column['searchable'] = true;
        }

        return $column;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [
            ['columnId' => 'direction', 'type' => 'set'],
            ['columnId' => 'outcome', 'type' => 'set'],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
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
        return [['columnId' => 'created_at', 'direction' => 'desc']];
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
        /** @var FormalabCallLog $row */
        return [
            'id' => $row->id,
            'created_at' => $row->created_at,
            'direction' => $row->direction,
            'method' => $row->method,
            'endpoint' => $row->endpoint,
            'status_code' => $row->status_code,
            'outcome' => $row->outcome,
            'duration_ms' => $row->duration_ms,
            'trigger' => $row->trigger,
            'error_message' => $row->error_message,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }
}
