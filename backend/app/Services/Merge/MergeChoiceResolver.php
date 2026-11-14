<?php

namespace App\Services\Merge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Checks the operator's choices against the real records (spec 0394, D-4/D-6,
 * AC-008) and turns them into the values the survivor ends up with.
 */
final class MergeChoiceResolver
{
    private const string TEXT_SEPARATOR = "\n\n";

    public function __construct(private readonly MergeFieldReader $reader) {}

    /**
     * @param  array<string, mixed>  $preview
     *
     * @throws ValidationException
     */
    public function assertValid(MergeDefinition $definition, MergeRequestData $data, array $preview): void
    {
        $errors = [];
        $fields = collect($definition->fields())->keyBy('key');
        $recordIds = [$data->survivorId, ...$data->mergedIds];

        foreach ($data->fields as $key => $choice) {
            $key = (string) $key;

            if (! $fields->has($key)) {
                $errors["fields.{$key}"] = __('Unknown field.');
            } elseif ($choice === MergeRequestData::COMBINE) {
                $fields[$key]->combinable || $errors["fields.{$key}"] = __('Only long text fields can be combined.');
            } elseif (! in_array($choice, $recordIds, true)) {
                $errors["fields.{$key}"] = __('The chosen record is not part of this merge.');
            }
        }

        $itemsByKey = collect($preview['collections'])->keyBy('key');

        foreach ($data->discard as $key => $ids) {
            $key = (string) $key;

            if (! $itemsByKey->has($key)) {
                $errors["discard.{$key}"] = __('Unknown collection.');

                continue;
            }

            $owned = collect($itemsByKey[$key]['items'])
                ->filter(static fn (array $item): bool => in_array($item['record_id'], $data->mergedIds, true))
                ->pluck('id')->all();

            if (array_diff($ids, $owned) !== []) {
                $errors["discard.{$key}"] = __('Only elements of the merged duplicates can be discarded.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The final value of every field, keyed by field key. A key missing from the
     * payload keeps the survivor's value.
     *
     * @param  Collection<int, Model>  $records  survivor first, then the duplicates by id
     * @return array<string, string|int|float|bool|null>
     */
    public function values(MergeDefinition $definition, Collection $records, MergeRequestData $data): array
    {
        $byId = $records->keyBy(static fn (Model $record): int => (int) $record->getKey());
        $values = [];

        foreach ($definition->fields() as $field) {
            $choice = $data->fields[$field->key] ?? $data->survivorId;

            $values[$field->key] = $choice === MergeRequestData::COMBINE
                ? $this->combine($field, $records)
                : $this->reader->value($field, $byId[(int) $choice]);
        }

        return $values;
    }

    /**
     * Non-empty texts joined survivor-first, identical ones once (D-4).
     *
     * @param  Collection<int, Model>  $records
     */
    private function combine(MergeField $field, Collection $records): ?string
    {
        $texts = $records
            ->map(fn (Model $record): string => trim((string) $this->reader->value($field, $record)))
            ->filter(static fn (string $text): bool => $text !== '')
            ->unique()
            ->all();

        return $texts === [] ? null : implode(self::TEXT_SEPARATOR, $texts);
    }
}
