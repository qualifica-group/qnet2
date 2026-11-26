<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One-shot (user request 2026-11-25): "Gare - Avvalimenti" (category 43)
 * takes every Offerta and Commessa field of the legacy "[OLD]
 * Avvalimenti_AVV" (category 320), with their forms.
 *
 * The fields are COPIED: the legacy category keeps them, because its offers
 * and work orders still show their values through them. Each field keeps its
 * requirement and sync flags and goes after the target's own fields, in the
 * source order. Each source form section is appended to the target form of
 * the same context and scope, so the target's own sections stay first.
 *
 * Identified by id, as the request gave them, with the names checked: on a
 * database where the ids name other categories the seeder stops.
 *
 * Idempotent: a second run finds every field and section in place and
 * writes nothing. Run with
 * `php artisan db:seed --class=QualificaAvvalimentiTenderFieldsSeeder`.
 */
class QualificaAvvalimentiTenderFieldsSeeder extends Seeder
{
    public const int SOURCE_ID = 320;

    public const string SOURCE_NAME = '[OLD] Avvalimenti_AVV';

    public const int TARGET_ID = 43;

    public const string TARGET_NAME = 'Gare - Avvalimenti';

    /** The contexts the request covers: Offerta and Commessa, not Prodotto. */
    private const array CONTEXTS = [AttributeContext::Quote, AttributeContext::WorkOrder];

    public function __construct(private readonly AttributeLayoutService $layouts) {}

    public function run(): void
    {
        $source = $this->category(self::SOURCE_ID, self::SOURCE_NAME);
        $target = $this->category(self::TARGET_ID, self::TARGET_NAME);
        $fields = 0;
        $sections = 0;

        DB::transaction(function () use ($source, $target, &$fields, &$sections): void {
            foreach (self::CONTEXTS as $context) {
                // Step 1: the fields first, the layout validator allows only assigned codes
                $fields += $this->copyFields($source, $target, $context);

                // Step 2: the source sections appended to the target form
                $sections += $this->copySections($source, $target, $context);
            }
        });

        $this->command?->info(sprintf('"%s": %d fields and %d form sections copied from "%s".', $target->name, $fields, $sections, $source->name));
    }

    private function category(int $id, string $name): ProductCategory
    {
        $category = ProductCategory::query()->findOrFail($id);

        if ($category->name !== $name) {
            throw new RuntimeException(sprintf('Category %d is "%s", expected "%s".', $id, $category->name, $name));
        }

        return $category;
    }

    private function copyFields(ProductCategory $source, ProductCategory $target, AttributeContext $context): int
    {
        $owned = $target->attributes()->wherePivot('context', $context->value);
        $existing = (clone $owned)->pluck('attributes.id')->all();
        $nextSortOrder = (int) (clone $owned)->max('attribute_category.sort_order') + 1;

        $missing = $source->attributes()
            ->wherePivot('context', $context->value)
            ->whereNotIn('attributes.id', $existing)
            ->orderBy('attribute_category.sort_order')
            ->get();

        $missing->values()->each(fn (Attribute $attribute, int $index) => $target->attributes()->attach($attribute->id, [
            'context' => $context->value,
            'is_required' => $attribute->pivot->is_required,
            'syncs_with_quote' => $attribute->pivot->syncs_with_quote,
            'sort_order' => $nextSortOrder + $index,
        ]));

        return $missing->count();
    }

    private function copySections(ProductCategory $source, ProductCategory $target, AttributeContext $context): int
    {
        $copied = 0;

        foreach (LayoutFormScope::cases() as $scope) {
            $sourceSections = $this->layouts->resolveExact($source, $context, $scope)['sections'] ?? [];
            $targetSections = $this->layouts->resolveExact($target, $context, $scope)['sections'] ?? [];
            $missing = $this->missingSections($sourceSections, $targetSections);

            if ($missing === []) {
                continue;
            }

            $this->layouts->upsert($target, $context, $scope, ['sections' => [...$targetSections, ...$this->placedAfter($missing, $targetSections)]]);
            $copied += count($missing);
        }

        return $copied;
    }

    /**
     * The source sections not yet in the target, by id.
     *
     * @param  array<int, array<string, mixed>>  $sourceSections
     * @param  array<int, array<string, mixed>>  $targetSections
     * @return list<array<string, mixed>>
     */
    private function missingSections(array $sourceSections, array $targetSections): array
    {
        $present = array_column($targetSections, 'id');

        return array_values(array_filter($sourceSections, static fn (array $section): bool => ! in_array($section['id'], $present, true)));
    }

    /**
     * The sections renumbered after the target's last one, in their own order.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<int, array<string, mixed>>  $targetSections
     * @return list<array<string, mixed>>
     */
    private function placedAfter(array $sections, array $targetSections): array
    {
        $last = max([-1, ...array_column($targetSections, 'sort_order')]);
        usort($sections, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return array_map(static fn (array $section, int $index): array => [...$section, 'sort_order' => $last + 1 + $index], $sections, array_keys($sections));
    }
}
