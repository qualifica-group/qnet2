<?php

use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0048 (C): PATCH /api/imports/{domain}/{importRun}/rows/assign gains
 * `mode` (single|balanced). `mode` absent (the ORIGINAL contract) stays
 * covered, untouched, by ImportBulkAssignTest.php (AC-020 retro-compat).
 * This file covers the NEW additive `mode` values.
 */
if (! function_exists('balancedAssignActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function balancedAssignActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        if (in_array('import', $abilities, true)) {
            grantImportRunsPermissions($user, ['update']);
        }

        return $user;
    }
}

if (! function_exists('balancedAssignOperatorAtSite')) {
    function balancedAssignOperatorAtSite(OperationalSite $site): User
    {
        $operator = User::factory()->create();
        EmploymentProfile::factory()->create(['user_id' => $operator->id, 'operational_site_id' => $site->id]);

        return $operator;
    }
}

// ---------------------------------------------------------------------------
// AC-020 — mode=single explicit: same effect as the original contract.
// ---------------------------------------------------------------------------

it('AC-020: explicit mode=single behaves like the original contract', function () {
    $actor = balancedAssignActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'single',
        'operator_id' => $operator->id,
        'row_ids' => [$row->id],
    ])->assertOk()->assertJsonPath('data.updated', 1);

    expect($row->fresh()->operator_id)->toBe($operator->id)
        ->and($row->fresh()->is_edited)->toBeTrue();
});

it('AC-020: explicit mode=single without operator_id is 422', function () {
    $actor = balancedAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'single',
        'row_ids' => [$row->id],
    ])->assertStatus(422)->assertJsonValidationErrors('operator_id');
});

// ---------------------------------------------------------------------------
// AC-021 — mode=balanced
// ---------------------------------------------------------------------------

it('AC-021: mode=balanced distributes the targeted rows across the Sede\'s operators', function () {
    $actor = balancedAssignActor(['import']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operatorA = balancedAssignOperatorAtSite($site);
    $operatorB = balancedAssignOperatorAtSite($site);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row1 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $row2 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'row_ids' => [$row1->id, $row2->id],
    ])->assertOk()->assertJsonPath('data.updated', 2);

    $assignedOperatorIds = collect([$row1->fresh()->operator_id, $row2->fresh()->operator_id]);

    expect($assignedOperatorIds->unique()->sort()->values()->all())->toBe([$operatorA->id, $operatorB->id])
        ->and($row1->fresh()->operational_site_id)->toBe($site->id)
        ->and($row1->fresh()->is_edited)->toBeTrue()
        ->and($row2->fresh()->operational_site_id)->toBe($site->id)
        ->and($row2->fresh()->is_edited)->toBeTrue();
});

it('AC-021: mode=balanced honors pre-existing REAL lead load, not just staged rows', function () {
    $actor = balancedAssignActor(['import']);
    $site = OperationalSite::factory()->withAddress()->create();
    $busyOperator = balancedAssignOperatorAtSite($site);
    $idleOperator = balancedAssignOperatorAtSite($site);
    Lead::factory()->count(3)->create(['operator_id' => $busyOperator->id]);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row1 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $row2 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'row_ids' => [$row1->id, $row2->id],
    ])->assertOk()->assertJsonPath('data.updated', 2);

    // Both rows go to the idle operator (0 vs 3 real leads) to catch up.
    expect($row1->fresh()->operator_id)->toBe($idleOperator->id)
        ->and($row2->fresh()->operator_id)->toBe($idleOperator->id);
});

it('AC-021: mode=balanced requires operational_site_id (422 without it)', function () {
    $actor = balancedAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$row->id],
    ])->assertStatus(422)->assertJsonValidationErrors('operational_site_id');
});

it('AC-021: mode=balanced on a Sede with zero operators is 422 and modifies nothing', function () {
    $actor = balancedAssignActor(['import']);
    $site = OperationalSite::factory()->withAddress()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'row_ids' => [$row->id],
    ])->assertStatus(422);

    expect($row->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// spec 0094 increment — mode=balanced combined with bulk `product_ids`
// ---------------------------------------------------------------------------

it('mode=balanced writes product_ids on every targeted row while still distributing operators', function () {
    $actor = balancedAssignActor(['import']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operatorA = balancedAssignOperatorAtSite($site);
    $operatorB = balancedAssignOperatorAtSite($site);
    $campaign = Campaign::factory()->create();
    $category = $campaign->productLines()->first()->productCategory;
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row1 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $row2 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'product_ids' => [$product->id],
        'row_ids' => [$row1->id, $row2->id],
    ])->assertOk()->assertJsonPath('data.updated', 2);

    $assignedOperatorIds = collect([$row1->fresh()->operator_id, $row2->fresh()->operator_id]);

    expect($assignedOperatorIds->unique()->sort()->values()->all())->toBe([$operatorA->id, $operatorB->id])
        ->and($row1->fresh()->product_ids)->toBe([$product->id])
        ->and($row1->fresh()->is_edited)->toBeTrue()
        ->and($row2->fresh()->product_ids)->toBe([$product->id])
        ->and($row2->fresh()->is_edited)->toBeTrue();
});
