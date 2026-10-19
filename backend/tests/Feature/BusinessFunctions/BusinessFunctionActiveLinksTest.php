<?php

use App\Enums\PurchaseRequestLineStatus;
use App\Models\BusinessFunction;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0280: a NEW link to an inactive business function is refused (D-2,
// D-7); what is already linked stays valid, and the function a product line
// derives from its category is never checked (D-3, D-4).

const INACTIVE_FUNCTION_MESSAGE = 'This business function is not active.';

// ---------------------------------------------------------------------------
// AC-004 — user membership, managed functions, competences
// ---------------------------------------------------------------------------

it('AC-004: a user cannot be made a member of an inactive function, nor of a child of one', function () {
    $inactive = BusinessFunction::factory()->inactive()->create();
    $child = BusinessFunction::factory()->childOf($inactive)->create();
    $target = User::factory()->create();
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$inactive->id, $child->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'employment.business_function_ids.0' => INACTIVE_FUNCTION_MESSAGE,
            'employment.business_function_ids.1' => INACTIVE_FUNCTION_MESSAGE,
        ]);
});

it('AC-004: a membership saved before the deactivation is re-sent freely, a new inactive one is not', function () {
    $kept = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->inactive()->create();
    $target = User::factory()->withEmployment(fn ($profile) => $profile->memberOf($kept))->create();
    $kept->update(['is_active' => false]);
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$kept->id]]])->assertOk();

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['business_function_ids' => [$kept->id, $other->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment.business_function_ids.1' => INACTIVE_FUNCTION_MESSAGE])
        ->assertJsonMissingValidationErrors('employment.business_function_ids.0');
});

it('AC-004: managed functions follow the same rule', function () {
    $managed = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->inactive()->create();
    $target = User::factory()->withEmployment(fn ($profile) => $profile->memberOf(BusinessFunction::factory()->create()))->create();
    $managed->update(['manager_id' => $target->id, 'is_active' => false]);
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['managed_business_function_ids' => [$managed->id]]])->assertOk();

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['managed_business_function_ids' => [$managed->id, $other->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment.managed_business_function_ids.1' => INACTIVE_FUNCTION_MESSAGE]);
});

it('AC-004: competence rows follow the same rule', function () {
    $saved = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->inactive()->create();
    $target = User::factory()->withEmployment(fn ($profile) => $profile->memberOf(BusinessFunction::factory()->create()))->create();
    $target->employment->productLines()->create(['business_function_id' => $saved->id, 'product_category_id' => null]);
    $saved->update(['is_active' => false]);
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $kept = [['business_function_id' => $saved->id, 'product_category_id' => null]];
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => $kept]])->assertOk();

    $added = [...$kept, ['business_function_id' => $other->id, 'product_category_id' => null]];
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => $added]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment.product_lines.1.business_function_id' => INACTIVE_FUNCTION_MESSAGE]);
});

// ---------------------------------------------------------------------------
// AC-005 — product category own function
// ---------------------------------------------------------------------------

it('AC-005: a category cannot get an inactive function, but keeps the one it already has', function () {
    $saved = BusinessFunction::factory()->create();
    $inactive = BusinessFunction::factory()->inactive()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $saved->id]);
    $saved->update(['is_active' => false]);
    Sanctum::actingAs(productCategoryUserWith(['create', 'update']));

    $this->postJson('/api/product-categories', ['name' => 'New', 'business_function_id' => $inactive->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['business_function_id' => INACTIVE_FUNCTION_MESSAGE]);

    $this->patchJson("/api/product-categories/{$category->id}", ['business_function_id' => $saved->id, 'name' => 'Renamed'])
        ->assertOk();
});

// ---------------------------------------------------------------------------
// AC-006 — purchase request
// ---------------------------------------------------------------------------

it('AC-006: a purchase request cannot be created on an inactive function', function () {
    Notification::fake();
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));
    $payload = purchaseRequestPayload(['business_function_id' => BusinessFunction::factory()->inactive()->create()->id]);

    $this->postJson('/api/purchase-requests', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['business_function_id' => INACTIVE_FUNCTION_MESSAGE]);
});

it('AC-006: a purchase request whose function was deactivated is still editable', function () {
    Notification::fake();
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines([PurchaseRequestLineStatus::PendingApproval]);
    BusinessFunction::query()->whereKey($request->business_function_id)->update(['is_active' => false]);

    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request, ['subject' => 'Renamed']))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// AC-007 — org chart
// ---------------------------------------------------------------------------

it('AC-007: a person cannot be moved into an inactive function', function () {
    $from = BusinessFunction::factory()->create();
    $to = BusinessFunction::factory()->inactive()->create();
    $person = orgChartPerson('Pat');
    $person->businessFunctions()->attach($from->id);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", ['from_function_id' => $from->id, 'to_function_id' => $to->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to_function_id' => INACTIVE_FUNCTION_MESSAGE]);
});

// ---------------------------------------------------------------------------
// AC-008 — derived function and coverage are untouched
// ---------------------------------------------------------------------------

it('AC-008: a product line still saves when its category function is inactive', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("projects.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('projects.create');
    $extras = projectStoreExtras();
    BusinessFunction::query()->whereKey($extras['product_lines'][0]['business_function_id'])->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Inactive function project',
        'pipeline_status_id' => PipelineStatus::factory()->create()->id,
        'country_id' => Country::factory()->create()->id,
        ...$extras,
    ])->assertCreated();
});

it('AC-008: a user whose only function is inactive is still saved', function () {
    $only = BusinessFunction::factory()->create();
    $target = User::factory()->withEmployment(fn ($profile) => $profile->memberOf($only))->create();
    $only->update(['is_active' => false]);
    Sanctum::actingAs(userWithUserAbilities(['update']));

    $this->patchJson("/api/users/{$target->id}", ['name' => 'Renamed', 'employment' => ['business_function_ids' => [$only->id]]])
        ->assertOk();
});
