<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
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

if (! function_exists('categoryTabAssertAttributeStaysHidden')) {
    /**
     * Saves `attr.<code>` hidden from the tab of $category, then reloads that
     * tab's columns: the column must still be there and still hidden (AC-008).
     */
    function categoryTabAssertAttributeStaysHidden(string $domain, ProductCategory $category, string $code): void
    {
        $columnId = "attr.{$code}";

        test()->postJson("/api/tables/{$domain}/preferences", [
            'columns' => [['id' => $columnId, 'visible' => false, 'order' => 1]],
            'product_category_id' => $category->id,
        ])->assertOk();

        $columns = test()->getJson("/api/tables/{$domain}/columns?product_category_id={$category->id}")->assertOk()->json('data.columns');

        expect(categoryTabColumn($columns, $columnId))->not->toBeNull()
            ->and(categoryTabColumn($columns, $columnId)['visible'])->toBeFalse();
    }
}
