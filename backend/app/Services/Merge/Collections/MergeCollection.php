<?php

namespace App\Services\Merge\Collections;

use App\Services\Merge\MergeItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A discardable collection of a mergeable record (spec 0394, D-6): reads the
 * elements of every record for the preview and moves the kept ones from the
 * duplicates to the survivor.
 */
interface MergeCollection
{
    /** Preview/discard key, e.g. `contacts`. */
    public function key(): string;

    /**
     * Every element of every record, in record order.
     *
     * @param  Collection<int, Model>  $records
     * @return list<MergeItem>
     */
    public function items(Collection $records): array;

    /**
     * Move to the survivor the elements of the duplicates that are neither
     * discarded nor already present on the survivor.
     *
     * @param  Collection<int, Model>  $merged
     * @param  list<int>  $discardedIds
     */
    public function apply(Model $survivor, Collection $merged, array $discardedIds): void;
}
