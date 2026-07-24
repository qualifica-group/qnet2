<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds the new mandatory 'validated' system row (App\Enums\WorkflowStatus
 * SystemKey::Validated) to every opportunity_workflow_statuses set (a
 * workflow's own, or the global default set) — the working phase now ends on
 * "Validato" before the terminal outcome (spec 0047, revised AC-004): the
 * pinned tail order becomes validated -> closed_won -> closed_lost.
 *
 * A data migration (not a schema change): the `system_key`/`group` columns
 * already fit the 'validated' value. Placed where 'closed_won' sits today; the
 * two closed-outcome rows are pushed one STEP down so nothing collides. Custom
 * rows are left untouched. Sets created AFTER this runs get their 'validated'
 * row from WorkflowStatusWriter directly.
 */
return new class extends Migration
{
    private const int STEP = 10;

    public function up(): void
    {
        foreach ($this->setIds() as $workflowId) {
            $closedWonSort = $this->scope($workflowId)->where('system_key', 'closed_won')->value('sort_order');

            // Push both closed-outcome rows one STEP down, then slot 'validated'
            // into the gap they vacated (or after the last row when a set has no
            // closed rows — defense in depth, should never happen post-0721).
            if ($closedWonSort !== null) {
                $this->scope($workflowId)
                    ->whereIn('system_key', ['closed_won', 'closed_lost'])
                    ->increment('sort_order', self::STEP);

                $validatedSort = (int) $closedWonSort;
            } else {
                $validatedSort = (int) $this->scope($workflowId)->max('sort_order') + self::STEP;
            }

            $this->insertValidatedRow($workflowId, $validatedSort);
        }
    }

    public function down(): void
    {
        foreach ($this->setIds() as $workflowId) {
            $hadValidated = $this->scope($workflowId)->where('system_key', 'validated')->exists();

            $this->scope($workflowId)->where('system_key', 'validated')->delete();

            if ($hadValidated) {
                $this->scope($workflowId)
                    ->whereIn('system_key', ['closed_won', 'closed_lost'])
                    ->decrement('sort_order', self::STEP);
            }
        }
    }

    /**
     * @return Collection<int, int|null>
     */
    private function setIds(): Collection
    {
        return DB::table('opportunity_workflow_statuses')
            ->select('opportunity_workflow_id')
            ->distinct()
            ->pluck('opportunity_workflow_id');
    }

    private function insertValidatedRow(?int $workflowId, int $sortOrder): void
    {
        $now = now();

        DB::table('opportunity_workflow_statuses')->insert([
            'opportunity_workflow_id' => $workflowId,
            'name' => 'Validato',
            'color' => null,
            'sort_order' => $sortOrder,
            'system_key' => 'validated',
            'group' => 'validated',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function scope(?int $workflowId): Builder
    {
        $query = DB::table('opportunity_workflow_statuses');

        return $workflowId === null
            ? $query->whereNull('opportunity_workflow_id')
            : $query->where('opportunity_workflow_id', $workflowId);
    }
};
