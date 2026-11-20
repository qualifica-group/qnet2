<?php

namespace App\Services\Merge;

use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Custom field values of a merge (spec 0394, D-10): the survivor keeps its own;
 * empty or missing keys take the first non-empty value of the duplicates, in id
 * order. Not chosen field by field.
 */
final class CustomFieldsMerger
{
    /**
     * @param  Collection<int, Model>  $merged  ordered by id
     */
    public function merge(string $entityType, Model $survivor, Collection $merged): void
    {
        $rows = CustomFieldValue::query()
            ->where('entity_type', $entityType)
            ->whereIn('entity_id', $merged->map(static fn (Model $record): int => (int) $record->getKey())->push($survivor->getKey())->all())
            ->get()
            ->keyBy('entity_id');

        $values = $rows->get($survivor->getKey())?->values ?? [];
        $original = $values;

        foreach ($merged as $record) {
            foreach ($rows->get($record->getKey())?->values ?? [] as $key => $value) {
                if ($this->isEmpty($values[$key] ?? null) && ! $this->isEmpty($value)) {
                    $values[$key] = $value;
                }
            }
        }

        if ($values !== $original) {
            CustomFieldValue::query()->updateOrCreate(
                ['entity_type' => $entityType, 'entity_id' => $survivor->getKey()],
                ['values' => $values],
            );
        }
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
