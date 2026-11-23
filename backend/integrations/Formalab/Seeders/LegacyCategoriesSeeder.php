<?php

declare(strict_types=1);

namespace Integrations\Formalab\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Services\CategorySettingsService;

/**
 * One-shot for the go-live (user request 2026-11-23, spec 0423 D-11):
 * `php artisan db:seed --class="Integrations\Formalab\Seeders\LegacyCategoriesSeeder" --force`.
 *
 * In the legacy QNet the Formalab class data belonged to the orders of the
 * "Formazione" services (`services.spec_service = 16`). Their categories,
 * found by `old_id`, get the link switched on at their branch root (the link
 * is root-owned) and their own field map: the course fields of the first
 * release (FormalabField suggestions) the category actually carries with a
 * compatible type, the course name included. Every other category of those
 * branches has no map, so its work orders stay out, as in the legacy.
 *
 * Idempotent: a category that already declares its own map (configured by
 * hand) keeps it; re-running changes nothing. A category not found or not
 * carrying the course name field is reported and skipped.
 */
final class LegacyCategoriesSeeder extends Seeder
{
    /**
     * Legacy `service_categories.id` of the services with spec_service 16
     * (read from the legacy DB on 2026-11-23): PROGETTI-, GOL _ Finanziato _
     * CAM, FOR_Iscritti GOL (solo iscritti), Autofinanziato _GEN,
     * FOR_Classi_GOL, FOR_AUTOIMPIEGO.
     */
    public const array LEGACY_CATEGORY_IDS = [74, 104, 114, 123, 142, 189];

    public function __construct(private readonly CategorySettingsService $settings) {}

    public function run(): void
    {
        foreach (self::LEGACY_CATEGORY_IDS as $legacyId) {
            $category = ProductCategory::query()->where('old_id', $legacyId)->first();

            if ($category === null) {
                $this->command?->warn("Formalab: legacy category {$legacyId} not found, skipped.");

                continue;
            }

            // Step 1: the link belongs to the branch root
            $root = $this->enableRoot($category);

            // Step 2: the category's own field map, unless one was already configured
            $this->mapCategory($category);

            $this->command?->info("Formalab: {$category->name} (root {$root->name}) linked.");
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
