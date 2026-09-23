<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\Notification;
use App\Models\User;
use App\Tables\Notifications\NotificationColumnCatalog;
use App\Tables\Notifications\NotificationDerivedColumns;
use App\Tables\Notifications\NotificationRowMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `notifications` domain (spec 0150): every
 * authenticated user's OWN notifications, browsable, filterable, with the
 * read/unread state and the two actions that flip it. Read-only otherwise:
 * no editable column, no delete action (D-2).
 *
 * baseQuery() is the SOLE authorization boundary for row visibility (D-1,
 * evolution of ADR-0005): scoped to the actor's own notifiable, FAIL-CLOSED
 * without one. NotificationPolicy exists only because the table framework's
 * authorizeViewAny() requires a Policy — every row it ever resolves already
 * belongs to the actor by construction, so actionsFor() below needs no
 * separate ownership re-check, only the row's own read state.
 *
 * `status`/`title`/`message`/`level` are DERIVED (no real `notifications`
 * column carries them): filter/sort/search/distinct-values are delegated to
 * NotificationDerivedColumns (file-size split, engineering.md §6) per
 * column, never to the generic engine's plain fallback (which would resolve
 * to a non-existent DB column).
 */
class NotificationsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly NotificationRowMapper $rowMapper,
        private readonly NotificationDerivedColumns $derivedColumns,
    ) {}

    public function domain(): string
    {
        return 'notifications';
    }

    /**
     * @return class-string<Notification>
     */
    public function modelClass(): string
    {
        return Notification::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives NotificationPolicy::viewAny
    // (true for every authenticated user, D-1).

    /**
     * @return Builder<Notification>
     */
    public function baseQuery(): Builder
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        if ($actor === null) {
            // FAIL-CLOSED (D-1): a condition that can never match a row,
            // never left unrestricted — same trick as TaskVisibilityScope.
            return Notification::query()->whereNull('notifications.id');
        }

        return Notification::query()
            ->where('notifiable_type', $actor->getMorphClass())
            ->where('notifiable_id', $actor->getKey());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return NotificationColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return NotificationColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return NotificationColumnCatalog::actions();
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
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return match ($columnId) {
            'status' => NotificationColumnCatalog::statusBadges(),
            'level' => NotificationColumnCatalog::levelBadges(),
            default => null,
        };
    }

    /**
     * The frontend localizes both badges' labels from its own i18n resources
     * (`enums.notification_status.<value>` / `enums.notification_level.<value>`).
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return match ($columnId) {
            'status' => 'notification_status',
            'level' => 'notification_level',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Notification $row */
        return $this->rowMapper->map($row);
    }

    /**
     * The row's own read state, never an ownership re-check: every row
     * reaching here already belongs to the actor (baseQuery's scoping).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var Notification $row */
        return [$row->read_at === null ? 'mark-read' : 'mark-unread'];
    }

    /**
     * No editable column exists for this domain (D-2: "nessuna modifica
     * inline"), so the row's `editable` flag must always be false —
     * decoupled from NotificationPolicy::update() (ownership), which governs
     * a different concern: whether the actor may reach the dedicated
     * mark-read/mark-unread endpoints, not inline grid editing.
     */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return false;
    }

    /**
     * @param  Builder<Notification>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->derivedColumns->applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<Notification>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return $this->derivedColumns->applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<Notification>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->derivedColumns->applySearch($query, $columnId, $pattern);
    }

    /**
     * @param  array<string, mixed>  $columnConfig
     * @param  Builder<Notification>  $query
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->derivedColumns->distinctValues($columnId, $columnConfig, $search);
    }
}
