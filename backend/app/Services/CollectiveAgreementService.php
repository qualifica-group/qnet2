<?php

namespace App\Services;

use App\DataObjects\CollectiveAgreements\CollectiveAgreementData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\CollectiveAgreement;
use App\Models\CollectiveAgreementLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the `collective-agreements` resource (spec 0272 D-3).
 */
class CollectiveAgreementService
{
    public function create(CollectiveAgreementData $data): CollectiveAgreement
    {
        return DB::transaction(function () use ($data): CollectiveAgreement {
            $agreement = CollectiveAgreement::create($data->attributes());
            $this->syncLevels($agreement, $data);

            return $this->withRelations($agreement);
        });
    }

    public function update(CollectiveAgreement $agreement, CollectiveAgreementData $data): CollectiveAgreement
    {
        return DB::transaction(function () use ($agreement, $data): CollectiveAgreement {
            $agreement->fill($data->attributes())->save();
            $this->syncLevels($agreement, $data);

            return $this->withRelations($agreement);
        });
    }

    /**
     * Restrictive delete (spec 0272 D-3): an agreement with a level used by a
     * contractual condition cannot be removed.
     */
    public function delete(CollectiveAgreement $agreement): void
    {
        $inUse = CollectiveAgreementLevel::query()
            ->where('collective_agreement_id', $agreement->id)
            ->whereHas('contracts')
            ->exists();

        if ($inUse) {
            abort(409, __('collective_agreements.in_use'));
        }

        $agreement->delete();
    }

    /**
     * Loads what CollectiveAgreementResource reads, so it never lazy-loads.
     */
    public function withRelations(CollectiveAgreement $agreement): CollectiveAgreement
    {
        return $agreement->load(['levels' => fn ($levels) => $levels->withCount('contracts')]);
    }

    /**
     * Levels as "AGREEMENT NAME – CODE" options; `ids[]` hydrate edit mode
     * (appended outside the page, never inflating the total).
     */
    public function levelsForSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = CollectiveAgreementLevel::query()->with('agreement:id,name');

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $base->where(fn (Builder $where) => $where
                ->where('collective_agreement_levels.code', 'like', $term)
                ->orWhereHas('agreement', fn (Builder $agreement) => $agreement->where('name', 'like', $term)));
        }

        $result = $query->page($base, fn (Builder $builder) => $this->orderedLevels($builder));

        if (! $query->hasIds()) {
            return $result;
        }

        $missing = array_values(array_diff($query->ids, $result->items->pluck('id')->all()));

        if ($missing === []) {
            return $result;
        }

        $hydrated = $this->orderedLevels(CollectiveAgreementLevel::query()->with('agreement:id,name'))->whereIn('collective_agreement_levels.id', $missing)->get();

        return new ForSelectResult($result->items->concat($hydrated), $result->total, $result->offset, $result->limit, $result->hasMore);
    }

    /**
     * @param  Builder<CollectiveAgreementLevel>  $query
     * @return Builder<CollectiveAgreementLevel>
     */
    private function orderedLevels(Builder $query): Builder
    {
        return $query
            ->select('collective_agreement_levels.*')
            ->join('collective_agreements', 'collective_agreements.id', '=', 'collective_agreement_levels.collective_agreement_id')
            ->orderBy('collective_agreements.name')
            ->orderBy('collective_agreement_levels.sort_order')
            ->orderBy('collective_agreement_levels.id');
    }

    /**
     * Replaces the levels with the submitted ordered list: keeps the rows
     * carrying an id, creates the others, deletes the absent ones.
     */
    private function syncLevels(CollectiveAgreement $agreement, CollectiveAgreementData $data): void
    {
        $submitted = $data->levels();

        if ($submitted === null) {
            return;
        }

        // Step 1: delete the absent levels, refusing those used by a condition
        $existing = $agreement->levels()->withCount('contracts')->get()->keyBy('id');
        $keptIds = array_filter(array_column($submitted, 'id'));
        $removed = $existing->except($keptIds);

        if ($removed->contains(fn (CollectiveAgreementLevel $level): bool => $level->contracts_count > 0)) {
            throw ValidationException::withMessages(['levels' => __('collective_agreements.levels_in_use')]);
        }

        $agreement->levels()->whereIn('id', $removed->modelKeys())->delete();

        // Step 2: park the changing codes so swaps do not hit the unique index
        foreach ($submitted as $row) {
            $level = $row['id'] === null ? null : $existing->get($row['id']);

            if ($level !== null && $level->code !== $row['code']) {
                $level->update(['code' => '~'.$level->id]);
            }
        }

        // Step 3: write the final values, the array index being the order
        foreach ($submitted as $index => $row) {
            $values = ['code' => $row['code'], 'description' => $row['description'], 'sort_order' => $index];

            $row['id'] === null
                ? $agreement->levels()->create($values)
                : $existing->get($row['id'])->update($values);
        }
    }
}
