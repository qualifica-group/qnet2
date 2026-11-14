<?php

namespace App\Services\Merge;

use App\Models\RecordMerge;
use App\Models\Referent;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy-migration view of merged records (spec 0394, D-12): a deleted
 * duplicate keeps resolving, through its legacy id, to the record it was merged
 * into (following chained merges), so the import neither recreates it nor
 * loses links to it.
 */
final class MergedRecordResolver
{
    /** @var array<class-string<Model>, string> */
    private const array ENTITIES = [
        Registry::class => 'registry',
        Referent::class => 'referent',
    ];

    /** Guard against a corrupt merge cycle. */
    private const int MAX_HOPS = 20;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function survivorId(string $modelClass, int|string $oldId): ?int
    {
        $entity = self::ENTITIES[$modelClass] ?? null;

        if ($entity === null) {
            return null;
        }

        $id = RecordMerge::query()->where('entity_type', $entity)->where('merged_old_id', $oldId)->latest('id')->value('survivor_id');

        for ($hop = 0; $id !== null && $hop < self::MAX_HOPS; $hop++) {
            if ($modelClass::query()->whereKey($id)->exists()) {
                return (int) $id;
            }

            $id = RecordMerge::query()->where('entity_type', $entity)->where('merged_id', $id)->latest('id')->value('survivor_id');
        }

        return null;
    }
}
