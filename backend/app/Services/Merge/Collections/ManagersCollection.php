<?php

namespace App\Services\Merge\Collections;

use App\Models\User;
use App\Services\Merge\MergeItem;
use App\Services\Merge\MergeItemDeduper;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The internal managers ("G.A. n") of a registry (spec 0394, D-7): the
 * survivor's keep their position, the duplicates' take the free ones in order.
 * No assignment notification is sent (written straight to the pivot).
 */
final class ManagersCollection implements MergeCollection
{
    public function key(): string
    {
        return 'managers';
    }

    public function items(Collection $records): array
    {
        $rows = DB::table('registry_user')
            ->whereIn('registry_id', $records->map(static fn (Model $record): int => (int) $record->getKey())->all())
            ->orderBy('registry_id')
            ->orderBy('position')
            ->get(['registry_id', 'user_id', 'position']);

        $names = User::query()->whereIn('id', $rows->pluck('user_id')->all())->pluck('name', 'id');

        return $rows->map(static fn (object $row): MergeItem => new MergeItem(
            id: (int) $row->user_id,
            recordId: (int) $row->registry_id,
            label: (string) ($names[$row->user_id] ?? '#'.$row->user_id),
            detail: (string) $row->position,
            matchKey: (string) $row->user_id,
        ))->all();
    }

    public function apply(Model $survivor, Collection $merged, array $discardedIds): void
    {
        $taken = DB::table('registry_user')->where('registry_id', $survivor->getKey())->pluck('position')->map(static fn ($p): int => (int) $p)->all();
        $free = array_values(array_diff(range(1, ManagerPositions::MAX), $taken));
        $moving = MergeItemDeduper::toMove($this->items(collect([$survivor])->concat($merged)), (int) $survivor->getKey(), $discardedIds);

        if (count($moving) > count($free)) {
            throw ValidationException::withMessages(['discard.managers' => __('Too many internal managers: discard some of them.')]);
        }

        foreach ($moving as $index => $item) {
            DB::table('registry_user')->insert(['registry_id' => $survivor->getKey(), 'user_id' => $item->id, 'position' => $free[$index]]);
        }
    }
}
