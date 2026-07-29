<?php

namespace App\Services\Statuses;

use App\Models\OpportunityStatus;
use App\Models\PipelineStatus;
use App\Models\QuoteStatus;
use App\Models\RewardStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * `sort_order` placement/resequencing for every status configurator (spec
 * 0039, D-5; extended to opportunity_statuses by spec 0043; extended to
 * reward_statuses by spec 0060; extended to quote_statuses by spec 0065):
 * server-managed since the field left store/update. Generic on the sibling
 * status models via a class-string (no speculative interface — engineering.md
 * §1.3): all four share the exact same name/system_key/sort_order shape,
 * differing only in which system row pins to the HEAD
 * (`$modelClass::SYSTEM_HEAD_KEY`) and which pin to the TAIL
 * (`$modelClass::SYSTEM_TAIL_KEYS` — PipelineStatus: `[Closed]`;
 * OpportunityStatus/QuoteStatus: `[Won, Lost]`; RewardStatus: `[]`, no tail —
 * spec 0060 D-3, "pending" is a head-only system row).
 *
 * Sequence invariant, maintained by every method here: SYSTEM_HEAD_KEY=0,
 * custom=10,20,..., then each SYSTEM_TAIL_KEYS row in declared order,
 * +STEP apart (e.g. lead: Chiuso con successo=max(custom)+10,
 * Scartato=max(custom)+20 — always last).
 */
class StatusOrderManager
{
    private const int STEP = 10;

    /**
     * The `sort_order` a brand-new custom row should be created with: the
     * last custom's order + STEP (or STEP, i.e. the tail's current first
     * slot, when there is no custom yet). The tail rows are bumped past it
     * in the same transaction so they always stay last, in their declared
     * order.
     *
     * @param  class-string<PipelineStatus>|class-string<OpportunityStatus>|class-string<RewardStatus>|class-string<QuoteStatus>  $modelClass
     */
    public function placeNew(string $modelClass): int
    {
        return DB::transaction(function () use ($modelClass): int {
            $lastCustomOrder = $modelClass::query()->whereNull('system_key')->max('sort_order');
            $newOrder = $lastCustomOrder !== null ? $lastCustomOrder + self::STEP : self::STEP;

            $this->bumpTail($modelClass, $newOrder);

            return $newOrder;
        });
    }

    /**
     * Resequences every custom row to $orderedIds' order (10, 20, ...),
     * renormalizes Nuovo=0 and the tail rows past the last custom, and
     * returns the fresh, complete, ordered list. $orderedIds must be
     * EXACTLY the set of custom ids (no system row, no duplicate, none
     * missing) — validated here so the guard holds regardless of caller
     * (defense in depth beyond the FormRequest's own `distinct` rule).
     *
     * @param  class-string<PipelineStatus>|class-string<OpportunityStatus>|class-string<RewardStatus>|class-string<QuoteStatus>  $modelClass
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, PipelineStatus|OpportunityStatus|RewardStatus|QuoteStatus>
     *
     * @throws HttpException 422
     */
    public function reorder(string $modelClass, array $orderedIds): Collection
    {
        return DB::transaction(function () use ($modelClass, $orderedIds): Collection {
            $this->assertValidReorderSet($modelClass, $orderedIds);

            $sortOrder = self::STEP;

            foreach ($orderedIds as $id) {
                $modelClass::query()->where('id', $id)->update(['sort_order' => $sortOrder]);
                $sortOrder += self::STEP;
            }

            $modelClass::query()->where('system_key', $modelClass::SYSTEM_HEAD_KEY->value)->update(['sort_order' => 0]);
            $this->bumpTail($modelClass, $sortOrder - self::STEP);

            return $modelClass::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
        });
    }

    /**
     * Places every `$modelClass::SYSTEM_TAIL_KEYS` row, in declared order,
     * STEP apart, starting right after $lastCustomOrder (the last custom
     * row's sort_order, or 0 when there is none).
     *
     * @param  class-string<PipelineStatus>|class-string<OpportunityStatus>|class-string<RewardStatus>|class-string<QuoteStatus>  $modelClass
     */
    private function bumpTail(string $modelClass, int $lastCustomOrder): void
    {
        $sortOrder = $lastCustomOrder;

        foreach ($modelClass::SYSTEM_TAIL_KEYS as $tailKey) {
            $sortOrder += self::STEP;

            $modelClass::query()->where('system_key', $tailKey->value)->update(['sort_order' => $sortOrder]);
        }
    }

    /**
     * $orderedIds must be exactly the custom (non-system) id set: no
     * duplicates, no system-row id, none missing.
     *
     * @param  class-string<PipelineStatus>|class-string<OpportunityStatus>|class-string<RewardStatus>|class-string<QuoteStatus>  $modelClass
     * @param  array<int, int>  $orderedIds
     *
     * @throws HttpException 422
     */
    private function assertValidReorderSet(string $modelClass, array $orderedIds): void
    {
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            abort(422, 'ordered_ids contains duplicate ids.');
        }

        $customIds = $modelClass::query()->whereNull('system_key')->pluck('id')->all();

        if (array_diff($orderedIds, $customIds) !== [] || array_diff($customIds, $orderedIds) !== []) {
            abort(422, 'ordered_ids must contain exactly the custom statuses (no system status, none missing).');
        }
    }
}
