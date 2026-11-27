<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Models\Note;
use App\Models\WorkOrder;
use App\Notes\Mentions\MentionParser;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The legacy `OrdersTable` "Ultima Nota" / "U.N. Responsabile" columns of the
 * commesse grid (user request 2026-11-27):
 * - `last_note_at` ("Ultima nota data"): the date of the commessa's most
 *   recent note, root or reply;
 * - `supervisor_last_note_at` ("Ultima nota responsabile"): the date of the
 *   most recent note written by one of its Responsabili.
 *
 * As in the legacy the cell shows the date and the note on hover: the row also
 * carries author and plain-text excerpt (`*_author`/`*_excerpt`). Values come
 * from the eager-loaded WorkOrder::latestNote()/latestSupervisorNote(); filter
 * and sort run on correlated subqueries with the same rule (soft-deleted notes
 * excluded), never a row-multiplying JOIN.
 */
final class WorkOrderLastNoteColumns
{
    public const string LAST_NOTE_AT = 'last_note_at';

    public const string SUPERVISOR_LAST_NOTE_AT = 'supervisor_last_note_at';

    /** @var array<int, string> */
    public const array EAGER_LOADS = ['latestNote.author', 'latestSupervisorNote.author'];

    /** Column id => WorkOrder relation holding the note. */
    private const array RELATIONS = [
        self::LAST_NOTE_AT => 'latestNote',
        self::SUPERVISOR_LAST_NOTE_AT => 'latestSupervisorNote',
    ];

    /** Characters of the note text shown in the hover. */
    private const int EXCERPT_LIMIT = 300;

    private const string DERIVED_TABLE = 'work_order_last_notes';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    public static function handles(string $columnId): bool
    {
        return array_key_exists($columnId, self::RELATIONS);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return array_map(static fn (string $id): array => [
            'id' => $id,
            'label' => "workOrders.columns.{$id}",
            'type' => 'date',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'date',
            'hasFilterValues' => false,
        ], array_keys(self::RELATIONS));
    }

    /**
     * @return array<int, array{columnId: string, type: string}>
     */
    public static function filters(): array
    {
        return array_map(static fn (string $id): array => ['columnId' => $id, 'type' => 'date'], array_keys(self::RELATIONS));
    }

    /**
     * @return array<string, mixed>
     */
    public function project(WorkOrder $workOrder): array
    {
        $row = [];

        foreach (self::RELATIONS as $columnId => $relation) {
            /** @var Note|null $note */
            $note = $workOrder->getRelation($relation);
            $row[$columnId] = $note?->created_at;
            $row[$columnId.'_author'] = $note?->author?->name;
            $row[$columnId.'_excerpt'] = $note === null ? null : MentionParser::excerptWithNames($note->body, [], self::EXCERPT_LIMIT);
        }

        return $row;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): void
    {
        $dates = WorkOrder::query()
            ->fromSub(
                WorkOrder::query()->select('work_orders.id')->selectSub($this->dateSubquery($columnId), $columnId)->toBase(),
                self::DERIVED_TABLE,
            )
            ->select(self::DERIVED_TABLE.'.id');

        $this->filterApplier->apply($dates, self::DERIVED_TABLE.'.'.$columnId, $columnConfig, $filter);

        $query->whereIn('work_orders.id', $dates);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): void
    {
        $query->orderBy($this->dateSubquery($columnId), $direction);
    }

    private function dateSubquery(string $columnId): QueryBuilder
    {
        $notes = DB::table('notes')
            ->selectRaw('MAX(notes.created_at)')
            ->where('notes.notable_type', (new WorkOrder)->getMorphClass())
            ->whereColumn('notes.notable_id', 'work_orders.id')
            ->whereNull('notes.deleted_at');

        if ($columnId === self::SUPERVISOR_LAST_NOTE_AT) {
            $notes->whereExists(static fn (QueryBuilder $supervisor) => $supervisor->from('work_order_supervisor')
                ->whereColumn('work_order_supervisor.work_order_id', 'work_orders.id')
                ->whereColumn('work_order_supervisor.user_id', 'notes.user_id'));
        }

        return $notes;
    }
}
