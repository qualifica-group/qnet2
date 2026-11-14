<?php

namespace App\Services\Merge\Collections;

use App\Services\Merge\MergeItem;
use App\Services\Merge\MergeItemDeduper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A plain many-to-many link (sectors, referents, registries) of the records
 * (spec 0394, D-6). The element id is the LINKED entity's id; the same entity on
 * two records is a duplicate.
 */
final class PivotCollection implements MergeCollection
{
    /**
     * @param  class-string<Model>  $relatedModel
     */
    public function __construct(
        private readonly string $key,
        private readonly string $table,
        private readonly string $ownerColumn,
        private readonly string $relatedColumn,
        private readonly string $relatedModel,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function items(Collection $records): array
    {
        $rows = DB::table($this->table)
            ->whereIn($this->ownerColumn, $records->map(static fn (Model $record): int => (int) $record->getKey())->all())
            ->orderBy($this->ownerColumn)
            ->orderBy('id')
            ->get([$this->ownerColumn, $this->relatedColumn]);

        $names = $this->relatedModel::query()->whereIn('id', $rows->pluck($this->relatedColumn)->all())->pluck('name', 'id');

        return $rows->map(fn (object $row): MergeItem => new MergeItem(
            id: (int) $row->{$this->relatedColumn},
            recordId: (int) $row->{$this->ownerColumn},
            label: (string) ($names[$row->{$this->relatedColumn}] ?? '#'.$row->{$this->relatedColumn}),
            detail: null,
            matchKey: (string) $row->{$this->relatedColumn},
        ))->all();
    }

    public function apply(Model $survivor, Collection $merged, array $discardedIds): void
    {
        $items = $this->items(collect([$survivor])->concat($merged));

        foreach (MergeItemDeduper::toMove($items, (int) $survivor->getKey(), $discardedIds) as $item) {
            DB::table($this->table)->insert([$this->ownerColumn => $survivor->getKey(), $this->relatedColumn => $item->id]);
        }
    }
}
