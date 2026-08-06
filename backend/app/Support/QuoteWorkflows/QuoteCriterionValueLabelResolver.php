<?php

declare(strict_types=1);

namespace App\Support\QuoteWorkflows;

use App\Models\QuoteWorkflowCriterion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Batch-resolves the human-readable `name` a criterion's `value_id` points at
 * (spec 0047, moved onto the Offerta by spec 0083 D-6: `value_label` on
 * QuoteWorkflowResource, the `criteria_values` table column) — grouped by
 * QuoteCriterionFieldRegistry::existsTable() and read with ONE query per
 * distinct table, never per-row (anti-N+1), shared by both consumers so the
 * resolution logic lives once.
 *
 * A caller that cannot use constructor injection (the API Resource) resolves
 * it via app().
 *
 * D10: a criterion whose `field` is no longer in the allow-list (its custom
 * field definition was disabled/deleted) is never queried — existsTable()
 * would throw for it — and falls back to its raw `value_id`, stringified.
 */
final class QuoteCriterionValueLabelResolver
{
    public function __construct(private readonly QuoteCriterionFieldRegistry $registry) {}

    /**
     * @param  Collection<int, QuoteWorkflowCriterion>  $criteria
     * @return array<int, string> criterion id => resolved label (falls back
     *                            to the raw value_id, stringified, when the referenced row no longer
     *                            exists, or when the field is no longer allow-listed, D10)
     */
    public function resolve(Collection $criteria): array
    {
        if ($criteria->isEmpty()) {
            return [];
        }

        $namesByTable = $criteria
            ->filter(fn (QuoteWorkflowCriterion $criterion): bool => $this->registry->isAllowed($criterion->field))
            ->groupBy(fn (QuoteWorkflowCriterion $criterion): string => $this->registry->existsTable($criterion->field))
            ->map(function (Collection $rows, string $table): Collection {
                $labelColumn = $this->registry->labelColumnFor($rows->first()->field);

                return DB::table($table)
                    ->whereIn('id', $rows->pluck('value_id')->unique()->all())
                    ->pluck($labelColumn, 'id');
            });

        return $criteria
            ->mapWithKeys(function (QuoteWorkflowCriterion $criterion) use ($namesByTable): array {
                if (! $this->registry->isAllowed($criterion->field)) {
                    return [$criterion->id => (string) $criterion->value_id];
                }

                $table = $this->registry->existsTable($criterion->field);
                $label = $namesByTable->get($table)?->get($criterion->value_id);

                return [$criterion->id => $label ?? (string) $criterion->value_id];
            })
            ->all();
    }
}
