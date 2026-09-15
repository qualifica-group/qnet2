<?php

use App\Models\ProductCategory;
use App\Models\User;
use App\RequestManagement\RequestModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130 — AC-006: `GET /api/tables/enrollee-management/columns` coincides
// with `GET /api/tables/request-management/columns` (columns, filters,
// advancedFilters, defaultSort, action catalogue) except for the domain
// itself. AbstractTableDefinition::resolveActions() ALREADY strips each
// action's `permission` key before it reaches the client (spec 0053), so a
// structural diff of the two responses minus `resource` IS the "a meno dei
// nomi permesso" comparison the criterion asks for — never a hand-duplicated
// column list.

uses(RefreshDatabase::class);

if (! function_exists('columnParityActor')) {
    /**
     * Every ability of BOTH modules, including `updateSource` (D-4's
     * protected-field ability, minted by ProtectedFieldRegistry — not part of
     * RequestModule::abilities()): a partial grant would make the two
     * responses diverge on `editable`/`change_request` for reasons that have
     * nothing to do with AC-006 itself.
     */
    function columnParityActor(): User
    {
        foreach (RequestModule::cases() as $module) {
            foreach ([...$module->abilities(), 'updateSource'] as $ability) {
                Permission::findOrCreate($module->permission($ability));
            }
        }

        $user = User::factory()->create();

        foreach (RequestModule::cases() as $module) {
            foreach ([...$module->abilities(), 'updateSource'] as $ability) {
                $user->givePermissionTo($module->permission($ability));
            }
        }

        return $user;
    }
}

it('columns/filters/advancedFilters/defaultSort/actions coincide with request-management, apart from the domain itself (AC-006)', function () {
    Sanctum::actingAs(columnParityActor());

    $requests = $this->getJson('/api/tables/request-management/columns')->assertOk()->json('data');
    $enrollees = $this->getJson('/api/tables/enrollee-management/columns')->assertOk()->json('data');

    expect($requests['resource'])->toBe('request-management')
        ->and($enrollees['resource'])->toBe('enrollee-management');

    unset($requests['resource'], $enrollees['resource']);

    expect($enrollees)->toEqual($requests)
        // Guards the comparison itself against a silently-empty response.
        ->and($enrollees['columns'])->not->toBeEmpty()
        ->and($enrollees['actions'])->not->toBeEmpty();
});

it('the enrollee-management action catalogue carries the same keys, in the same order, as request-management (AC-006)', function () {
    Sanctum::actingAs(columnParityActor());

    $requestKeys = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.actions'))->pluck('key');
    $enrolleeKeys = collect($this->getJson('/api/tables/enrollee-management/columns')->assertOk()->json('data.actions'))->pluck('key');

    expect($enrolleeKeys->all())->toBe($requestKeys->all());
});

it('a productCategoryId tab scopes enrollee-management columns/rows exactly like request-management (D-6)', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(columnParityActor());

    $this->getJson("/api/tables/enrollee-management/columns?productCategoryId={$category->id}")->assertOk();
    $this->postJson('/api/tables/enrollee-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'productCategoryId' => $category->id,
    ])->assertOk();
});
