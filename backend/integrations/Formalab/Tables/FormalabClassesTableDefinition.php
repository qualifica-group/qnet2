<?php

declare(strict_types=1);

namespace Integrations\Formalab\Tables;

use App\Models\User;
use App\Services\UserService;
use App\Tables\AbstractTableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Tables\Concerns\BuildsColumns;

/**
 * The `formalab-classes` table domain (spec 0426 D-9): the local copy of the
 * Formalab class catalogue, read-only (Formalab owns it, the sync writes it).
 * Super-admin only, like the rest of the integration pages. Sort and filter
 * run on the fixed column allow-list below.
 */
class FormalabClassesTableDefinition extends AbstractTableDefinition
{
    use BuildsColumns;

    private const string LABELS = 'formalab:classes.columns';

    public function domain(): string
    {
        return 'formalab-classes';
    }

    /**
     * @return class-string<FormalabClass>
     */
    public function modelClass(): string
    {
        return FormalabClass::class;
    }

    public function authorizeViewAny(User $actor): bool
    {
        return $actor->hasRole(UserService::PRIVILEGED_ROLE);
    }

    /** The catalogue belongs to Formalab: never edited here. */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return false;
    }

    /** The catalogue belongs to Formalab: never deleted here. */
    public function authorizeDelete(User $actor, Model $row): bool
    {
        return false;
    }

    /**
     * @return Builder<FormalabClass>
     */
    public function baseQuery(): Builder
    {
        return FormalabClass::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            $this->column('edition_id', 'number', sortable: true, filter: 'number', searchable: true),
            $this->column('code', 'text', sortable: true, filter: 'text', searchable: true),
            $this->column('title', 'text', sortable: true, filter: 'text', searchable: true),
            $this->column('status', 'text', sortable: true, filter: 'text'),
            $this->column('course_status', 'text', sortable: true, filter: 'text'),
            $this->column('start_date', 'date', sortable: true, filter: 'date'),
            $this->column('end_date', 'date', sortable: true, filter: 'date'),
            $this->column('total_hours', 'number', sortable: true, filter: 'number'),
            $this->column('exam_date', 'date', sortable: true, filter: 'date'),
            $this->column('site_name', 'text', sortable: true, filter: 'text'),
            $this->column('site_city', 'text', sortable: true, filter: 'text'),
            $this->column('learners_count', 'number', sortable: true, filter: 'number'),
            $this->column('last_seen_at', 'datetime', visible: false, sortable: true, filter: 'date'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [
            ['columnId' => 'code', 'type' => 'text'],
            ['columnId' => 'title', 'type' => 'text'],
            ['columnId' => 'start_date', 'type' => 'date'],
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
        return [['columnId' => 'start_date', 'direction' => 'desc']];
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
        /** @var FormalabClass $row */
        return [
            'id' => $row->edition_id,
            'edition_id' => $row->edition_id,
            'code' => $row->code,
            'title' => $row->title,
            'status' => $row->status,
            'course_status' => $row->course_status,
            'start_date' => $row->start_date?->toDateString(),
            'end_date' => $row->end_date?->toDateString(),
            'total_hours' => $row->total_hours,
            'exam_date' => $row->exam_date?->toDateString(),
            'site_name' => $row->site_name,
            'site_city' => $row->site_city,
            'learners_count' => $row->learners_count,
            'last_seen_at' => $row->last_seen_at,
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
