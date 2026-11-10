<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\RequestManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Altri gestori account" (spec 0382 D-11): in a category tab with labels the
 * `managers` column holds only the people in the positions the category does
 * not name, since the named ones have a column each. Row value, filter,
 * distinct values and cell write all leave the named positions alone. In
 * "Tutte" none of this applies: the column is the whole team (spec 0373).
 */
final class RequestOtherManagersColumn
{
    public function __construct(
        private readonly RequestManagerSlotColumn $slotColumn,
        private readonly RequestTeamCellWriter $teamCellWriter,
        private readonly RequestManagementService $service,
    ) {}

    /**
     * The mapped row with the team narrowed to the unnamed positions.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<int, int>  $namedPositions
     * @return array<string, mixed>
     */
    public function narrowRow(array $mapped, array $namedPositions): array
    {
        $team = $mapped[RequestManagerColumns::MANAGERS_COLUMN_ID] ?? null;

        if ($namedPositions === [] || ! is_array($team)) {
            return $mapped;
        }

        $mapped[RequestManagerColumns::MANAGERS_COLUMN_ID] = array_values(array_filter(
            $team,
            static fn (array $person): bool => ! in_array($person['position'] ?? null, $namedPositions, true),
        ));

        return $mapped;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     * @param  array<int, int>  $namedPositions
     */
    public function applyFilter(Builder $query, array $filter, array $namedPositions): void
    {
        $this->slotColumn->applyFilter($query, RequestManagerColumns::MANAGERS_COLUMN_ID, $filter, $namedPositions);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, int>  $namedPositions
     * @return array<int, string|null>|null
     */
    public function distinctValues(?string $search, Builder $query, int $limit, array $namedPositions): ?array
    {
        return $this->slotColumn->distinctValues(RequestManagerColumns::MANAGERS_COLUMN_ID, $search, $query, $limit, $namedPositions);
    }

    /**
     * Writes the cell's people into the unnamed positions, through the work
     * panel's own rules (RequestTeamCellWriter); the named ones stay as they are.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int>  $namedPositions
     */
    public function write(Quote $quote, User $actor, RequestModule $module, array $userIds, array $namedPositions): Quote
    {
        $payload = $this->teamCellWriter->payload($quote, $actor, $module, $userIds, $namedPositions);

        return $this->service->updateWork($quote, $actor, $payload)['quote'];
    }
}
