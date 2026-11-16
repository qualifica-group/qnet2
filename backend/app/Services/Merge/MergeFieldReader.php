<?php

namespace App\Services\Merge;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Reads the mergeable scalar fields off a record and its card (spec 0394, D-4)
 * as plain wire values: enums as their value, dates as Y-m-d, '' as null.
 */
final class MergeFieldReader
{
    public function value(MergeField $field, Model $record): string|int|float|bool|null
    {
        $source = $field->onCard ? $record->getRelationValue('personalData') : $record;
        $raw = $source?->getAttribute($field->key);

        return match (true) {
            $raw instanceof BackedEnum => $raw->value,
            $raw instanceof DateTimeInterface => $raw->format('Y-m-d'),
            $raw === '' => null,
            default => $raw,
        };
    }

    /**
     * Readable names of the related rows of every `relation` field, as
     * `[fieldKey => [id => name]]`.
     *
     * @param  list<MergeField>  $fields
     * @param  Collection<int, Model>  $records
     * @return array<string, array<int, string>>
     */
    public function displays(array $fields, Collection $records): array
    {
        $displays = [];

        foreach ($fields as $field) {
            if ($field->relationModel === null) {
                continue;
            }

            $ids = $records->map(fn (Model $record) => $this->value($field, $record))->filter()->unique()->all();
            $displays[$field->key] = $field->relationModel::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
        }

        return $displays;
    }
}
