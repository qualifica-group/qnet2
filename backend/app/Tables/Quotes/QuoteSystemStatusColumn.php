<?php

declare(strict_types=1);

namespace App\Tables\Quotes;

use App\Models\Quote;
use App\Models\QuoteSystemStatus;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `quote_system_status` column of the Offerte grid (spec 0404 A-D7): the
 * system status linked by the offer's working status, two hops away
 * (quotes -> quote_workflow_statuses -> quote_system_statuses). Set filter by
 * name with "(Vuoti)", sort by name, Excel-like distinct values, and the
 * select editor's catalogue. Bound values and fixed identifiers only.
 */
final class QuoteSystemStatusColumn
{
    use HandlesBlankSetFilter;

    public const string COLUMN_ID = 'quote_system_status';

    /** Nested relation the set filter matches (`whereHas`). */
    public const string RELATION = 'quoteWorkflowStatus.systemStatus';

    private const int MAX_FILTER_VALUES = 200;

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyNames(Builder $query, array $values, bool $matchesBlank = false): void
    {
        $values = array_slice($values, 0, self::MAX_FILTER_VALUES);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas(self::RELATION, static fn (Builder $status) => $status->whereIn('name', $values));
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave(self::RELATION);
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $values = array_values(array_filter(
            is_array($filter['values'] ?? null) ? $filter['values'] : [],
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        $this->applyNames($query, $values, $this->matchesBlankEntry($filter));
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy(
            DB::table('quote_workflow_statuses')
                ->join('quote_system_statuses', 'quote_system_statuses.id', '=', 'quote_workflow_statuses.quote_system_status_id')
                ->select('quote_system_statuses.name')
                ->whereColumn('quote_workflow_statuses.id', 'quotes.quote_workflow_status_id')
                ->limit(1),
            $direction,
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        $workingStatusIds = (clone $query)->select('quotes.quote_workflow_status_id');

        $names = QuoteSystemStatus::query()
            ->whereIn('id', DB::table('quote_workflow_statuses')->whereIn('id', $workingStatusIds)->select('quote_system_status_id'))
            ->when($search !== null && $search !== '', static fn ($builder) => $builder->where('name', 'like', '%'.addcslashes((string) $search, '%_\\').'%'))
            ->orderBy('sort_order')
            ->limit($limit)
            ->pluck('name')
            ->all();

        return $this->withBlankEntry($names, $search, fn (): bool => (clone $query)->whereDoesntHave(self::RELATION)->exists());
    }

    /**
     * @return array{id: int, name: string, color: string|null}|null
     */
    public function summarize(Quote $quote): ?array
    {
        $status = $quote->quoteWorkflowStatus?->systemStatus;

        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }

    /**
     * The select editor's catalogue: every system status, per-row narrowing
     * and note markers ride on the row (`_options`, `_notes`).
     *
     * @return array<int, array{value: int, label: string, color: string|null, requires_note: bool}>
     */
    public static function catalog(): array
    {
        return QuoteSystemStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color'])
            ->map(static fn (QuoteSystemStatus $status): array => [
                'value' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'requires_note' => false,
            ])
            ->all();
    }

    /**
     * Name options of the advanced filter.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function nameOptions(): array
    {
        return QuoteSystemStatus::query()
            ->orderBy('sort_order')
            ->pluck('name')
            ->map(static fn (string $name): array => ['value' => $name, 'label' => $name])
            ->all();
    }
}
