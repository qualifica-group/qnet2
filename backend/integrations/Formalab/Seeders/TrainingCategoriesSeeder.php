<?php

declare(strict_types=1);

namespace Integrations\Formalab\Seeders;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Services\CategorySettingsService;
use Integrations\Formalab\Support\FormalabField;

/**
 * One-shot for the go-live (user requests 2026-11-23, spec 0423 D-11/D-12):
 * `php artisan db:seed --class="Integrations\Formalab\Seeders\TrainingCategoriesSeeder" --force`.
 *
 * Links to Formalab, with the course fields mapped, the training categories:
 *  - the legacy ones: in the legacy QNet the class data belonged to the orders
 *    of the "Formazione" services (`services.spec_service = 16`); their
 *    categories are found by `old_id` and each gets its own map;
 *  - the current "Formazione" root: it has no course field, so the course
 *    attributes (the FormalabField suggestions) are first assigned to it in the
 *    work order context, which every subcategory inherits, then mapped on it.
 *
 * The link is switched on at each branch root (it is root-owned); a category
 * of those branches without a map stays out. Idempotent: an attribute already
 * assigned is not added again, a map configured by hand is kept, re-running
 * changes nothing. A category not found, or without the course name field, is
 * reported and skipped.
 */
final class TrainingCategoriesSeeder extends Seeder
{
    /**
     * Legacy `service_categories.id` of the services with spec_service 16
     * (read from the legacy DB on 2026-11-23): PROGETTI-, GOL _ Finanziato _
     * CAM, FOR_Iscritti GOL (solo iscritti), Autofinanziato _GEN,
     * FOR_Classi_GOL, FOR_AUTOIMPIEGO.
     */
    public const array LEGACY_CATEGORY_IDS = [74, 104, 114, 123, 142, 189];

    /** The current (non-legacy) training root category. */
    public const string CURRENT_ROOT_NAME = 'Formazione';

    public function __construct(private readonly CategorySettingsService $settings) {}

    public function run(): void
    {
        // Step 1: the legacy training categories, each with its own map
        foreach (self::LEGACY_CATEGORY_IDS as $legacyId) {
            $category = ProductCategory::query()->where('old_id', $legacyId)->first();

            if ($category === null) {
                $this->command?->warn("Formalab: legacy category {$legacyId} not found, skipped.");

                continue;
            }

            $this->link($category);
        }

        // Step 2: the current training root, once it carries the course fields
        $current = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('is_legacy', false)
            ->where('name', self::CURRENT_ROOT_NAME)
            ->first();

        if ($current === null) {
            $this->command?->warn('Formalab: category "'.self::CURRENT_ROOT_NAME.'" not found, skipped.');

            return;
        }

        $this->assignCourseAttributes($current);
        $this->link($current);
    }

    /** Switches the link on at the branch root and maps the category. */
    private function link(ProductCategory $category): void
    {
        $root = $this->enableRoot($category);
        $this->mapCategory($category);

        $this->command?->info("Formalab: {$category->name} (root {$root->name}) linked.");
    }

    /**
     * Assigns to the category, in the work order context, the course
     * attributes it does not carry yet, after its own ones and not required.
     */
    private function assignCourseAttributes(ProductCategory $category): void
    {
        $codes = array_values(array_filter(array_map(
            static fn (FormalabField $field): ?string => $field->suggestedAttributeCode(),
            FormalabField::cases(),
        )));
        $attributes = Attribute::query()->whereIn('code', $codes)->get()->keyBy('code');
        $assigned = DB::table('attribute_category')
            ->where('category_id', $category->id)
            ->where('context', AttributeContext::WorkOrder->value)
            ->pluck('attribute_id')
            ->map(intval(...))
            ->all();
        $position = (int) DB::table('attribute_category')
            ->where('category_id', $category->id)
            ->where('context', AttributeContext::WorkOrder->value)
            ->max('sort_order');

        foreach ($codes as $code) {
            $attribute = $attributes->get($code);

            if ($attribute === null) {
                $this->command?->warn("Formalab: attribute {$code} not found, not assigned to {$category->name}.");

                continue;
            }

            if (! in_array($attribute->id, $assigned, true)) {
                $category->attributes()->attach($attribute->id, [
                    'is_required' => false, 'sort_order' => ++$position, 'context' => AttributeContext::WorkOrder->value,
                ]);
            }
        }
    }

    private function enableRoot(ProductCategory $category): ProductCategory
    {
        $root = $category;

        while ($root->parent_id !== null && ($parent = ProductCategory::query()->find($root->parent_id)) !== null) {
            $root = $parent;
        }

        $setting = FormalabCategorySetting::query()->firstOrNew(['product_category_id' => $root->id]);
        $setting->enabled = true;
        $setting->save();

        return $root;
    }

    private function mapCategory(ProductCategory $category): void
    {
        $view = $this->settings->show($category);

        if ($view['own_field_map']) {
            $this->command?->line("Formalab: {$category->name} already has its own map, kept.");

            return;
        }

        $map = array_values(array_map(
            static fn (array $field): array => ['formalab_field' => $field['key'], 'attribute_code' => $field['suggested_attribute_code']],
            array_filter($view['fields'], static fn (array $field): bool => $field['suggested_attribute_code'] !== null),
        ));

        if ($map === []) {
            $this->command?->warn("Formalab: {$category->name} has none of the course fields, not mapped.");

            return;
        }

        try {
            $this->settings->update($category, null, $map, null);
        } catch (ValidationException $exception) {
            $this->command?->warn("Formalab: {$category->name} not mapped: ".collect($exception->errors())->flatten()->implode(' '));
        }
    }
}
