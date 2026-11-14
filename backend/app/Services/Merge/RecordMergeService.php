<?php

namespace App\Services\Merge;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\ProfileData;
use App\Enums\PersonalDataTypeEnum;
use App\Models\RecordMerge;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Merges duplicate registries/referents into one survivor (spec 0394): the
 * operator-chosen values and kept collection elements land on the survivor,
 * every module link moves to it and the duplicates are deleted, all in one
 * transaction. Entity differences live in the MergeDefinition.
 */
final class RecordMergeService
{
    public function __construct(
        private readonly MergePreviewBuilder $previewBuilder,
        private readonly MergeChoiceResolver $choices,
        private readonly CustomFieldsMerger $customFields,
        private readonly MergeReferenceReassigner $reassigner,
        private readonly RelationValueRewriter $relationValues,
        private readonly MergeWriteGuard $writeGuard,
    ) {}

    /**
     * The records with their card, ordered by id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Model>
     */
    public function records(MergeDefinition $definition, array $ids): Collection
    {
        return $definition->modelClass()::query()->with('personalData')->whereIn('id', $ids)->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array<string, mixed>
     */
    public function preview(MergeDefinition $definition, Collection $records): array
    {
        return $this->previewBuilder->build($definition, $records);
    }

    public function handle(User $actor, MergeDefinition $definition, MergeRequestData $data): Model
    {
        // Step 1: load the survivor and the duplicates (duplicates by id)
        $records = $this->records($definition, [$data->survivorId, ...$data->mergedIds]);
        $survivor = $records->firstWhere('id', $data->survivorId);
        $merged = $records->reject(static fn (Model $record): bool => $record->getKey() === $data->survivorId)->values();
        $ordered = collect([$survivor])->concat($merged);

        // Step 2: validate the choices and resolve the final values before touching anything
        ['preview' => $preview, 'counts' => $counts] = $this->previewBuilder->buildDetailed($definition, $records);
        $this->choices->assertValid($definition, $data, $preview);
        $values = $this->choices->values($definition, $ordered, $data);
        $this->writeGuard->assertAllowed($actor, $definition, $ordered, $data, $values);
        $profile = new ProfileData($this->cardData($values));
        $snapshots = $this->snapshots($merged, $preview, $counts, $data);

        try {
            DB::transaction(function () use ($actor, $definition, $data, $survivor, $merged, $values, $profile, $snapshots): void {
                $mergedIds = $merged->map(static fn (Model $record): int => (int) $record->getKey())->all();
                $this->lockRecords($definition, [(int) $survivor->getKey(), ...$mergedIds]);

                // Step 3: custom field values and collection elements
                $this->customFields->merge($definition->table(), $survivor, $merged);

                foreach ($definition->collections() as $collection) {
                    $collection->apply($survivor, $merged, $data->discard[$collection->key()] ?? []);
                }

                // Step 4: every module link moves to the survivor
                $dropped = $this->reassigner->reassign($definition, (int) $survivor->getKey(), $mergedIds);
                $this->relationValues->rewrite($definition->table(), array_fill_keys($mergedIds, (int) $survivor->getKey()));

                // Step 5: remove the duplicates, then write the chosen values (frees unique identities first)
                foreach ($merged as $record) {
                    $record->delete();
                }

                $this->writeSurvivor($definition, $survivor, $values, $profile);
                $definition->settle($survivor->refresh());

                // Step 6: trace the merge
                $this->recordMerges($actor, $definition, $survivor, $merged, $snapshots, $dropped);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['merged_ids' => __('The merge would create a duplicate that is not allowed (for example the same purchase invoice number for the same supplier).')]);
        }

        return $definition->loadTree($survivor->refresh());
    }

    /**
     * Serializes concurrent merges on the same records: the second one waits,
     * then finds a record the first already merged away and stops (D-14).
     *
     * @param  list<int>  $ids
     */
    private function lockRecords(MergeDefinition $definition, array $ids): void
    {
        $locked = $definition->modelClass()::query()->whereKey($ids)->lockForUpdate()->pluck('id');

        if ($locked->count() !== count($ids)) {
            abort(409, __('One of the records has just been merged or deleted: reload the list and try again.'));
        }
    }

    /**
     * The card the operator composed, valid for its type (D-5).
     *
     * @param  array<string, mixed>  $values
     */
    private function cardData(array $values): CreatePersonalData
    {
        $type = PersonalDataTypeEnum::tryFrom((string) $values['type']);

        if ($type === null) {
            throw ValidationException::withMessages(['fields.type' => __('The resulting record needs a valid type.')]);
        }

        $required = $type === PersonalDataTypeEnum::Company ? ['company_name'] : ['first_name', 'last_name'];
        $missing = array_filter($required, static fn (string $key): bool => trim((string) $values[$key]) === '');

        if ($missing !== []) {
            throw ValidationException::withMessages(array_combine(
                array_map(static fn (string $key): string => "fields.{$key}", $missing),
                array_fill(0, count($missing), __('The resulting record needs this value.')),
            ));
        }

        return new CreatePersonalData(
            type: $type,
            firstName: $values['first_name'],
            lastName: $values['last_name'],
            companyName: $values['company_name'],
            taxCode: $values['tax_code'],
            vatNumber: $values['vat_number'],
            sdiCode: $values['sdi_code'],
            birthDate: $values['birth_date'],
            birthCityId: $values['birth_city_id'],
            residenceCityId: $values['residence_city_id'],
            gender: $values['gender'],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function writeSurvivor(MergeDefinition $definition, Model $survivor, array $values, ProfileData $profile): void
    {
        $own = [];

        foreach ($definition->fields() as $field) {
            if (! $field->onCard) {
                $own[$field->key] = $field->type === MergeField::TYPE_BOOLEAN ? (bool) $values[$field->key] : $values[$field->key];
            }
        }

        $survivor->forceFill($own)->save();
        $definition->writeCard($survivor, $profile);
    }

    /**
     * Per duplicate: its preview slice and what the operator discarded (D-11).
     *
     * @param  Collection<int, Model>  $merged
     * @param  array<string, mixed>  $preview
     * @param  array<string, array<int, int>>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function snapshots(Collection $merged, array $preview, array $counts, MergeRequestData $data): array
    {
        $snapshots = [];

        foreach ($merged as $record) {
            $recordId = (int) $record->getKey();
            $snapshot = $this->previewBuilder->sliceFor($preview, $counts, $recordId);
            $snapshot['discarded'] = collect($snapshot['collections'])
                ->mapWithKeys(static fn (array $collection): array => [
                    $collection['key'] => array_values(array_filter(
                        $collection['items'],
                        static fn (array $item): bool => in_array($item['id'], $data->discard[$collection['key']] ?? [], true),
                    )),
                ])
                ->filter()
                ->all();
            $snapshots[$recordId] = $snapshot;
        }

        return $snapshots;
    }

    /**
     * @param  Collection<int, Model>  $merged
     * @param  array<int, array<string, mixed>>  $snapshots
     * @param  array<int, list<array{table: string, row: array<string, mixed>}>>  $dropped
     */
    private function recordMerges(User $actor, MergeDefinition $definition, Model $survivor, Collection $merged, array $snapshots, array $dropped): void
    {
        foreach ($merged as $record) {
            RecordMerge::query()->create([
                'entity_type' => $definition->entity(),
                'survivor_id' => $survivor->getKey(),
                'merged_id' => $record->getKey(),
                'merged_old_id' => $record->getAttribute('old_id'),
                'merged_name' => $record->getAttribute('name'),
                'snapshot' => [...$snapshots[$record->getKey()], 'dropped' => $dropped[$record->getKey()] ?? []],
                'merged_by' => $actor->getKey(),
            ]);
        }

        activity($survivor->getTable())
            ->performedOn($survivor)
            ->causedBy($actor)
            ->event('merged')
            ->withProperties(['merged' => $merged->map(static fn (Model $record): array => ['id' => $record->getKey(), 'name' => $record->getAttribute('name')])->all()])
            ->log('Records merged');
    }
}
