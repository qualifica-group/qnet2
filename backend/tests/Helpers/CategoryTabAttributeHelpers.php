<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

// Shared by the attr.<code> category-tab grid tests (spec 0350).

if (! function_exists('categoryTabAttribute')) {
    /**
     * A category with one attribute of $type assigned in $context.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{0: ProductCategory, 1: Attribute}
     */
    function categoryTabAttribute(AttributeContext $context, string $type = 'text', array $overrides = [], ?ProductCategory $category = null): array
    {
        $category ??= ProductCategory::factory()->create();
        $attribute = $type === 'enum'
            ? Attribute::factory()->enum(2)->create($overrides)
            : Attribute::factory()->ofType($type)->create($overrides);

        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => $context->value]);

        return [$category, $attribute];
    }
}

if (! function_exists('filterDrivenAttrIds')) {
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, string>
     */
    function filterDrivenAttrIds(array $columns): array
    {
        return array_values(array_filter(array_column($columns, 'id'), static fn (string $id): bool => str_starts_with($id, 'attr.')));
    }
}

if (! function_exists('categoryTabColumn')) {
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, mixed>|null
     */
    function categoryTabColumn(array $columns, string $id): ?array
    {
        return collect($columns)->firstWhere('id', $id);
    }
}

if (! function_exists('categoryTabAttributeRoleActor')) {
    /**
     * A role-bearing actor with the grid's viewAny/viewAll/update and ONE
     * role_field_permissions row on `attribute_values` (only a role-bearing
     * actor is restricted by the DB matrix).
     */
    function categoryTabAttributeRoleActor(string $grid, bool $editable): User
    {
        $abilities = array_map(static fn (string $ability): string => "{$grid}.{$ability}", ['viewAny', 'viewAll', 'update']);

        foreach ($abilities as $ability) {
            Permission::findOrCreate($ability);
        }

        $role = Role::create(['name' => 'tab-attr-role-'.uniqid()]);
        $role->givePermissionTo($abilities);
        $role->fieldPermissions()->create(['resource' => $grid, 'field' => 'attribute_values', 'visible' => true, 'editable' => $editable, 'required' => false]);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// Spec 0374: the flexible columns follow the category the advanced filters select.

if (! function_exists('categoryTabFilters')) {
    /**
     * The advanced filters selecting one product category.
     *
     * @return array<string, array<int, int>>
     */
    function categoryTabFilters(ProductCategory $category): array
    {
        return ['product_category' => [$category->id]];
    }
}

if (! function_exists('categoryTabSaveFilters')) {
    /**
     * Saves the advanced filters of the acting user for $grid and returns the response.
     *
     * @param  array<string, mixed>  $advancedFilters
     */
    function categoryTabSaveFilters(string $grid, array $advancedFilters, array $extra = []): TestResponse
    {
        return test()->postJson("/api/tables/{$grid}/filters", ['advancedFilters' => $advancedFilters, ...$extra]);
    }
}

if (! function_exists('categoryTabConfigColumns')) {
    /**
     * The columns of the config a page asks for with `attribute_columns_from_filters=1`.
     *
     * @return array<int, array<string, mixed>>
     */
    function categoryTabConfigColumns(string $grid): array
    {
        return test()->getJson("/api/tables/{$grid}/columns?attribute_columns_from_filters=1")->assertOk()->json('data.columns');
    }
}

if (! function_exists('categoryTabAssertAttributeStaysHidden')) {
    /**
     * Saves the filters of $category, hides `attr.<code>` through the
     * preferences, then reloads the config: the column must still be there and
     * still hidden (AC-013).
     */
    function categoryTabAssertAttributeStaysHidden(string $domain, ProductCategory $category, string $code): void
    {
        $columnId = "attr.{$code}";
        categoryTabSaveFilters($domain, categoryTabFilters($category))->assertOk();

        $saved = test()->postJson("/api/tables/{$domain}/preferences", [
            'columns' => [['id' => $columnId, 'visible' => false, 'order' => 1]],
            'attribute_columns_from_filters' => 1,
        ])->assertOk()->json('data.columns');
        $reloaded = categoryTabConfigColumns($domain);

        expect(categoryTabColumn($saved, $columnId)['visible'])->toBeFalse()
            ->and(categoryTabColumn($reloaded, $columnId))->not->toBeNull()
            ->and(categoryTabColumn($reloaded, $columnId)['visible'])->toBeFalse();
    }
}

if (! function_exists('categoryTabCsvRows')) {
    /**
     * The rows of a generated CSV export, header included.
     *
     * @return array<int, array<int, string>>
     */
    function categoryTabCsvRows(string $csv): array
    {
        $lines = array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n")));

        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
    }
}

if (! function_exists('categoryTabLegacyRoot')) {
    /**
     * A legacy root category, linked to $current when given (spec 0374, D-9).
     */
    function categoryTabLegacyRoot(?ProductCategory $current = null, string $name = '[OLD] Legacy'): ProductCategory
    {
        $legacy = ProductCategory::factory()->create(['name' => $name]);
        $legacy->forceFill(['is_legacy' => true, 'current_category_id' => $current?->id])->save();

        return $legacy;
    }
}
