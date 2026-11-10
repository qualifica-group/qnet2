<?php

declare(strict_types=1);

namespace App\Services;

use App\CustomFields\CustomFieldProvider;
use App\DataObjects\CustomFields\CreateCustomFieldData;
use App\DataObjects\CustomFields\UpdateCustomFieldData;
use App\Jobs\PromoteCustomFieldIndexJob;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Services\Deletion\InUseGuard;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `custom-fields` admin resource (spec 0021 — ADMIN
 * CRUD DEFINIZIONI): create/update (options full-replace, entity_type/type/
 * key immutability once the field has recorded values) and delete (refused while a
 * record holds a value). Every mutation busts CustomFieldProvider's per-entity_type cache
 * so the read-side decorators and the write pipeline never see a stale
 * definition. The controller stays thin; this Service is the single
 * authority.
 */
class CustomFieldService
{
    public function __construct(
        private readonly CustomFieldProvider $provider,
        private readonly InUseGuard $inUseGuard,
    ) {}

    public function create(CreateCustomFieldData $data): CustomFieldDefinition
    {
        return DB::transaction(function () use ($data): CustomFieldDefinition {
            $this->guardEnumHasOptions($data->type, count($data->options ?? []));
            $this->guardRelationHasTarget($data->type, $data->relationTarget);

            /** @var CustomFieldDefinition $definition */
            $definition = CustomFieldDefinition::create([
                'entity_type' => $data->entityType,
                'key' => $data->key,
                'type' => $data->type,
                'label' => $data->label,
                'description' => $data->description,
                'help_text' => $data->helpText,
                'placeholder' => $data->placeholder,
                'icon' => $data->icon,
                'group' => $data->group,
                'tab' => $data->tab,
                'sort_order' => $data->sortOrder,
                'config' => $data->config,
                'validation' => $data->validation,
                'relation_target' => $data->relationTarget,
                'is_indexed' => $data->isIndexed,
                'is_active' => $data->isActive,
            ]);

            if ($data->hasOptions()) {
                $this->replaceOptions($definition, $data->options);
            }

            $this->provider->forget($definition->entity_type);
            $this->dispatchIndexPromotionIfNewlyIndexed($definition, wasIndexed: false);

            return $definition->fresh('options');
        });
    }

    public function update(CustomFieldDefinition $definition, UpdateCustomFieldData $data): CustomFieldDefinition
    {
        $this->guardImmutableFields($definition, $data);

        $wasIndexed = $definition->is_indexed;
        $originalEntityType = $definition->entity_type;

        return DB::transaction(function () use ($definition, $data, $wasIndexed, $originalEntityType): CustomFieldDefinition {
            $finalType = $data->hasType() ? $data->type : $definition->type;
            $finalRelationTarget = $data->relationTargetSubmitted ? $data->relationTarget : $definition->relation_target;

            $attributes = $data->submittedAttributes();

            if ($data->hasType()) {
                $attributes['type'] = $finalType;
            }

            if ($data->hasEntityType()) {
                $attributes['entity_type'] = $data->entityType;
            }

            if ($data->hasKey()) {
                $attributes['key'] = $data->key;
            }

            if ($attributes !== []) {
                $definition->update($attributes);
            }

            if ($data->hasOptions()) {
                $this->guardEnumHasOptions($finalType, count($data->options));
                $this->replaceOptions($definition, $data->options);
            } else {
                // options untouched: the ENUM-needs-at-least-one-option
                // invariant must still hold against the PERSISTED count
                // (relevant when type is changing TO enum without a fresh
                // options payload).
                $this->guardEnumHasOptions($finalType, $definition->options()->count());
            }

            $this->guardRelationHasTarget($finalType, $finalRelationTarget);

            $this->provider->forget($originalEntityType);

            if ($data->hasEntityType() && $data->entityType !== $originalEntityType) {
                $this->provider->forget($data->entityType);
            }

            $this->dispatchIndexPromotionIfNewlyIndexed($definition, $wasIndexed);

            return $definition->fresh('options');
        });
    }

    /**
     * Delete a definition that no record holds a value for (spec 0384 D-6,
     * supersedes the purge of spec 0021).
     */
    public function delete(CustomFieldDefinition $definition): void
    {
        $this->inUseGuard->assertNoUsage(['records' => $this->recordedValuesCount($definition)]);

        $definition->delete();

        $this->provider->forget($definition->entity_type);
    }

    /**
     * An ENUM field must always carry at least one option.
     */
    private function guardEnumHasOptions(string $type, int $optionsCount): void
    {
        if ($type === 'enum' && $optionsCount === 0) {
            abort(422, 'At least one option is required for an ENUM field.');
        }
    }

    /**
     * A RELATION field must always carry a relation_target.
     *
     * @param  array<string, mixed>|null  $relationTarget
     */
    private function guardRelationHasTarget(string $type, ?array $relationTarget): void
    {
        if ($type === 'relation' && $relationTarget === null) {
            abort(422, 'A relation_target is required for a RELATION field.');
        }
    }

    /**
     * entity_type/type/key are immutable once the field has recorded values
     * (changing them would leave the existing `custom_field_values` entries
     * meaningless for that key).
     */
    private function guardImmutableFields(CustomFieldDefinition $definition, UpdateCustomFieldData $data): void
    {
        $changingEntityType = $data->hasEntityType() && $data->entityType !== $definition->entity_type;
        $changingType = $data->hasType() && $data->type !== $definition->type;
        $changingKey = $data->hasKey() && $data->key !== $definition->key;

        if (! $changingEntityType && ! $changingType && ! $changingKey) {
            return;
        }

        if ($this->recordedValuesCount($definition) > 0) {
            abort(422, 'entity_type, type and key are immutable once the field has recorded values.');
        }
    }

    /**
     * How many `custom_field_values` rows for this definition's entity_type
     * carry a value under its key — a bound JSON-path existence check
     * (never whereRaw on input; the key comes from the persisted definition,
     * already constrained to /^[a-z0-9_]+$/ on write).
     */
    private function recordedValuesCount(CustomFieldDefinition $definition): int
    {
        return CustomFieldValue::query()
            ->where('entity_type', $definition->entity_type)
            ->whereJsonContainsKey('values->'.$definition->key)
            ->count();
    }

    /**
     * Full-replace of the definition's option list: delete then recreate
     * inside the caller's transaction, so a failure never half-applies.
     *
     * @param  array<int, array{value: string, label: string, color?: string|null, icon?: string|null, sort_order?: int, is_default?: bool}>  $options
     */
    private function replaceOptions(CustomFieldDefinition $definition, array $options): void
    {
        $definition->options()->delete();

        $rows = [];

        foreach ($options as $index => $option) {
            $rows[] = [
                'value' => (string) $option['value'],
                'label' => (string) $option['label'],
                'color' => $option['color'] ?? null,
                'icon' => $option['icon'] ?? null,
                'sort_order' => (int) ($option['sort_order'] ?? $index),
                'is_default' => (bool) ($option['is_default'] ?? false),
            ];
        }

        $definition->options()->createMany($rows);
    }

    /**
     * Dispatch the index-promotion job (spec 0021 — PERFORMANCE /
     * INDICIZZAZIONE lane, AC-021, T15) to the queue when `is_indexed` just
     * transitioned to true. The job itself is a no-op on non-MySQL drivers
     * and idempotent on re-run (see PromoteCustomFieldIndexJob).
     */
    private function dispatchIndexPromotionIfNewlyIndexed(CustomFieldDefinition $definition, bool $wasIndexed): void
    {
        if ($wasIndexed || ! $definition->is_indexed) {
            return;
        }

        PromoteCustomFieldIndexJob::dispatch($definition->id);
    }
}
