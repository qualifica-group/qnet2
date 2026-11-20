<?php

declare(strict_types=1);

namespace App\CustomFields;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Services\Notifications\TableRowNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Persists the write pipeline's pending payload into `custom_field_values`
 * (spec 0021 — INNESTO WRITE, AC-010/AC-012/AC-013): one JSON row per
 * (entity_type, entity_id), upserted transactionally from
 * App\Models\Concerns\HasCustomFields' `saved`/`deleting` observers.
 */
class CustomFieldWriter
{
    public function __construct(
        private readonly CustomFieldEntityRegistry $entityRegistry,
        private readonly CustomFieldProvider $provider,
        private readonly FieldTypeRegistry $typeRegistry,
        private readonly TableRowNotifier $tableRowNotifier,
    ) {}

    /**
     * Pulls (destructively) the pending values off the request bag and
     * merges them into $model's `custom_field_values` row. No-op when
     * $model is not custom-fieldable or the bag has nothing pending for it
     * (see CustomFieldRequestBag's single-primary-entity assumption).
     */
    public function writeFromRequestBag(Model $model): void
    {
        $entityType = $this->entityRegistry->entityTypeForModel($model);
        $bag = app(CustomFieldRequestBag::class);

        if ($entityType === null || ! $bag->has()) {
            return;
        }

        $this->write($model, $entityType, $bag->pull());
    }

    /**
     * Upsert $model's custom_field_values row, merging $values into whatever
     * is already stored (PATCH semantics, AC-012): a key already persisted
     * but not resubmitted here is left untouched. Unknown keys (no active
     * definition) are dropped defensively — the validator is the actual
     * gate, this is a last line of defence against a stale/tampered payload.
     *
     * @param  array<string, mixed>  $values
     */
    public function write(Model $model, string $entityType, array $values): void
    {
        if ($values === []) {
            return;
        }

        $definitions = $this->provider->definitionsFor($entityType)->keyBy('key');
        $normalized = $this->normalize($definitions, $values);

        if ($normalized === []) {
            return;
        }

        DB::transaction(function () use ($model, $entityType, $normalized, $definitions): void {
            $row = CustomFieldValue::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $model->getKey())
                ->lockForUpdate()
                ->first();

            $before = $row?->values ?? [];

            if ($row === null) {
                CustomFieldValue::create([
                    'entity_type' => $entityType,
                    'entity_id' => $model->getKey(),
                    'values' => $normalized,
                ]);
            } else {
                $row->update(['values' => [...$before, ...$normalized]]);
            }

            // A table row that fired its event tells who the field names (spec 0390).
            $this->tableRowNotifier->notifyCustomFields($model, $definitions, $before, [...$before, ...$normalized]);
        });
    }

    /**
     * Remove $model's custom_field_values row entirely (AC-013 cleanup).
     * No-op when $model is not custom-fieldable.
     */
    public function purge(Model $model): void
    {
        $entityType = $this->entityRegistry->entityTypeForModel($model);

        if ($entityType === null) {
            return;
        }

        CustomFieldValue::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $model->getKey())
            ->delete();
    }

    /**
     * @param  Collection<string, CustomFieldDefinition>  $definitions
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(Collection $definitions, array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $definition = $definitions->get($key);

            if ($definition === null) {
                continue;
            }

            $normalized[$key] = $this->typeRegistry->resolve($definition->type)->normalizeForStore($value, $definition);
        }

        return $normalized;
    }
}
