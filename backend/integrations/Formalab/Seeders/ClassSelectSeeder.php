<?php

declare(strict_types=1);

namespace Integrations\Formalab\Seeders;

use App\Models\Attribute;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\CategoryFieldMapResolver;
use Integrations\Formalab\Support\FormalabField;

/**
 * One-shot of spec 0426 D-10, after `php artisan migrate` and a catalogue
 * sync, before TrainingCategoriesSeeder on a fresh install:
 * `php artisan db:seed --class="Integrations\Formalab\Seeders\ClassSelectSeeder" --force`.
 *
 * The course name attributes (the ones mapped on `label`, plus the suggested
 * `gol_corso`) become a relation onto the Formalab classes, and their work
 * order values turn from "code - title" text into the edition id: the one of
 * the work order's link, else the only class with that label. A value matching
 * no class is emptied and listed. Idempotent: a converted attribute and an
 * edition id already in place are left alone.
 */
final class ClassSelectSeeder extends Seeder
{
    private const int CHUNK = 500;

    public function __construct(private readonly CategoryFieldMapResolver $maps) {}

    public function run(): void
    {
        foreach (Attribute::query()->whereIn('code', $this->courseNameCodes())->get() as $attribute) {
            // Step 1: the attribute becomes the class select
            if (! $this->convertAttribute($attribute)) {
                continue;
            }

            // Step 2: its work order values become edition ids
            $this->convertValues($attribute->code);
        }
    }

    /**
     * @return list<string>
     */
    private function courseNameCodes(): array
    {
        $mapped = array_map(
            static fn (array $map): ?string => CategoryFieldMapResolver::toAssociative($map)[FormalabField::Label->value] ?? null,
            FormalabCategorySetting::query()->whereNotNull('field_map')->pluck('field_map')->all(),
        );

        return array_values(array_unique(array_filter([...$mapped, FormalabField::Label->suggestedAttributeCode()])));
    }

    /** @return bool false when the attribute is a relation onto something else (left alone) */
    private function convertAttribute(Attribute $attribute): bool
    {
        $target = $attribute->relation_target['entity_type'] ?? null;

        if ($attribute->type === 'relation') {
            if ($target !== FormalabClass::ENTITY_TYPE) {
                $this->command?->warn("Formalab: {$attribute->code} is a relation onto {$target}, not converted.");
            }

            return $target === FormalabClass::ENTITY_TYPE;
        }

        $attribute->forceFill([
            'type' => 'relation',
            'relation_target' => [
                'entity_type' => FormalabClass::ENTITY_TYPE,
                'cardinality' => 'one',
                'for_select_resource' => FormalabClass::FOR_SELECT_RESOURCE,
            ],
        ])->save();

        $this->command?->info("Formalab: {$attribute->code} is now the Formalab class select.");

        return true;
    }

    private function convertValues(string $code): void
    {
        $editionsByLabel = FormalabClass::query()->get(['edition_id', 'label'])->groupBy('label')
            ->map(static fn ($classes): ?int => $classes->count() === 1 ? $classes->first()->edition_id : null);
        $catalogued = FormalabClass::query()->pluck('edition_id')->flip();
        $linked = FormalabWorkOrderLink::query()->pluck('edition_id', 'work_order_id');
        $counts = ['converted' => 0, 'emptied' => 0];

        WorkOrder::query()
            ->whereNotNull("attribute_values->{$code}")
            ->chunkById(self::CHUNK, function ($workOrders) use ($code, $editionsByLabel, $catalogued, $linked, &$counts): void {
                foreach ($workOrders as $workOrder) {
                    $value = $workOrder->attribute_values[$code] ?? null;

                    if ($value === null || $value === '' || (is_int($value) && $catalogued->has($value))) {
                        continue;
                    }

                    $editionId = $linked->get($workOrder->id) ?? $editionsByLabel->get(trim((string) $value));
                    $this->store($workOrder, $code, $editionId);

                    if ($editionId === null) {
                        $counts['emptied']++;
                        $this->command?->warn("Formalab: work order {$workOrder->code} ({$workOrder->id}) \"{$value}\" matches no class, emptied.");
                    } else {
                        $counts['converted']++;
                    }
                }
            });

        $this->command?->info("Formalab: {$code} values converted {$counts['converted']}, emptied {$counts['emptied']}.");
    }

    /** A data conversion: no event, no activity entry, no timestamp change. */
    private function store(WorkOrder $workOrder, string $code, ?int $editionId): void
    {
        $values = $workOrder->attribute_values;

        if ($editionId === null) {
            unset($values[$code]);
        } else {
            $values[$code] = $editionId;
        }

        $workOrder->timestamps = false;
        $workOrder->forceFill(['attribute_values' => $values])->saveQuietly();
    }
}
