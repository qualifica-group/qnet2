<?php

use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\QuoteWorkflow;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0047, amendment 2026-07-27: custom relation fields as workflow
// criteria (D5-D11, AC-027..AC-030/AC-034/AC-037 — endpoint-level; AC-031/
// AC-032 live in the Unit resolver test, AC-033 in
// QuoteWorkflowCustomFieldOnCreateTest).
uses(RefreshDatabase::class);

if (! function_exists('quoteWorkflowUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteWorkflowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quote-workflows.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quote-workflows.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('companyRelationCustomField')) {
    /**
     * An active `relation` custom field on `opportunities`, targeting
     * `companies` (display column `denomination`, NOT `name` — exercises the
     * display-column heuristic beyond the native fields' hardcoded `name`).
     *
     * Signature (including the default) MUST stay identical to the
     * same-named helper in QuoteCriterionFieldRegistryTest.php:
     * whichever file Pest loads first wins the definition (function_exists
     * guard).
     */
    function companyRelationCustomField(string $cardinality = 'one'): CustomFieldDefinition
    {
        return CustomFieldDefinition::factory()->forEntity('opportunities')->create([
            'key' => 'preferred_company',
            'type' => 'relation',
            'label' => 'Azienda preferita',
            'relation_target' => ['entity_type' => 'companies', 'cardinality' => $cardinality, 'for_select_resource' => 'companies'],
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-027/AC-028 — GET /api/quote-workflows/criterion-fields
// ---------------------------------------------------------------------------

it('criterion-fields: includes an active relation custom field of entity_type opportunities, source=custom (AC-027)', function () {
    $definition = companyRelationCustomField(cardinality: 'many');
    $actor = quoteWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/quote-workflows/criterion-fields')->assertOk()->json('data');

    // 4 natives (spec 0092 added `product_category_branch_id`) + 1 custom.
    expect($data)->toHaveCount(5);

    $custom = collect($data)->firstWhere('field', "custom.{$definition->key}");

    expect($custom)->not->toBeNull()
        ->and($custom['source'])->toBe('custom')
        ->and($custom['label'])->toBe('Azienda preferita')
        ->and($custom['for_select_resource'])->toBe('companies')
        ->and($custom['multi_valued'])->toBeTrue();
});

it('criterion-fields: excludes a custom field of a different entity_type, a different type, or inactive (AC-028)', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->create(['type' => 'relation', 'key' => 'wrong_entity', 'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies']]);
    CustomFieldDefinition::factory()->forEntity('opportunities')->create(['type' => 'text', 'key' => 'wrong_type']);
    CustomFieldDefinition::factory()->forEntity('opportunities')->inactive()->create(['type' => 'relation', 'key' => 'inactive_relation', 'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies']]);

    $actor = quoteWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/quote-workflows/criterion-fields')->assertOk()->json('data');

    // The 4 natives only (spec 0092 added `product_category_branch_id`).
    expect($data)->toHaveCount(4)
        ->and(collect($data)->pluck('field'))->not->toContain('custom.wrong_entity', 'custom.wrong_type', 'custom.inactive_relation');
});

it('criterion-fields: excludes a relation definition with an unresolvable target or empty for_select_resource, and rejects it as a criterion field (AC-029)', function () {
    $unresolvable = CustomFieldDefinition::factory()->forEntity('opportunities')->create([
        'type' => 'relation',
        'key' => 'broken_target',
        'relation_target' => ['entity_type' => 'not-a-registered-domain', 'cardinality' => 'one', 'for_select_resource' => 'not-a-registered-domain'],
    ]);
    $emptySelect = CustomFieldDefinition::factory()->forEntity('opportunities')->create([
        'type' => 'relation',
        'key' => 'no_select_resource',
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => ''],
    ]);

    $actor = quoteWorkflowUserWith(['view', 'create']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/quote-workflows/criterion-fields')->assertOk()->json('data');

    expect(collect($data)->pluck('field'))->not->toContain('custom.broken_target', 'custom.no_select_resource');

    $this->postJson('/api/quote-workflows', [
        'name' => 'Uses an excluded custom field',
        'criteria' => [['field' => "custom.{$unresolvable->key}", 'value_id' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria.0.field');

    $this->postJson('/api/quote-workflows', [
        'name' => 'Uses an excluded custom field 2',
        'criteria' => [['field' => "custom.{$emptySelect->key}", 'value_id' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria.0.field');
});

// ---------------------------------------------------------------------------
// AC-030 — POST/PATCH persists field='custom.<key>'; bad value_id -> 422
// ---------------------------------------------------------------------------

it('create: 201 with a custom relation criterion, persisted with field=custom.<key> (AC-030)', function () {
    $definition = companyRelationCustomField();
    $company = Company::factory()->create();
    $actor = quoteWorkflowUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/quote-workflows', [
        'name' => 'Custom relation workflow',
        'criteria' => [['field' => "custom.{$definition->key}", 'value_id' => $company->id]],
    ])->assertCreated();

    $response->assertJsonPath('data.criteria.0.field', "custom.{$definition->key}")
        ->assertJsonPath('data.criteria.0.field_source', 'custom')
        ->assertJsonPath('data.criteria.0.field_label', 'Azienda preferita')
        ->assertJsonPath('data.criteria.0.value_label', $company->denomination);

    $this->assertDatabaseHas('quote_workflow_criteria', [
        'field' => "custom.{$definition->key}",
        'value_id' => $company->id,
    ]);
});

it('create: 422 when the custom relation criterion value_id does not exist on the target table (AC-030)', function () {
    $definition = companyRelationCustomField();
    $actor = quoteWorkflowUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-workflows', [
        'name' => 'Bad company id',
        'criteria' => [['field' => "custom.{$definition->key}", 'value_id' => 999999]],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria.0.value_id');
});

it('update: PATCH re-syncs a custom relation criterion (AC-030)', function () {
    $definition = companyRelationCustomField();
    $companyB = Company::factory()->create();
    $source = Source::factory()->create();
    $actor = quoteWorkflowUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quote-workflows', [
        'name' => 'Patchable',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/quote-workflows/{$created}", [
        'criteria' => [['field' => "custom.{$definition->key}", 'value_id' => $companyB->id]],
    ])->assertOk()
        ->assertJsonPath('data.criteria.0.field', "custom.{$definition->key}")
        ->assertJsonPath('data.criteria.0.value_id', $companyB->id);

    $this->assertDatabaseMissing('quote_workflow_criteria', ['field' => 'source_id']);
});

// ---------------------------------------------------------------------------
// AC-034 — disabling/deleting the referenced definition never 500s
// ---------------------------------------------------------------------------

it('show/table stay 200 after the referenced custom field definition is disabled (AC-034)', function () {
    $definition = companyRelationCustomField();
    $company = Company::factory()->create();
    $actor = quoteWorkflowUserWith(['view', 'viewAny']);
    Sanctum::actingAs($actor);

    $workflow = QuoteWorkflow::factory()->create();
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => $company->id]);

    $definition->update(['is_active' => false]);

    $this->getJson("/api/quote-workflows/{$workflow->id}")->assertOk()
        ->assertJsonPath('data.criteria.0.field_label', "custom.{$definition->key}")
        ->assertJsonPath('data.criteria.0.field_source', 'native')
        ->assertJsonPath('data.criteria.0.value_label', (string) $company->id);

    $this->postJson('/api/tables/quote-workflows/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk();
});

it('show/table stay 200 after the referenced custom field definition is deleted (AC-034)', function () {
    $definition = companyRelationCustomField();
    $company = Company::factory()->create();
    $actor = quoteWorkflowUserWith(['view', 'viewAny']);
    Sanctum::actingAs($actor);

    $workflow = QuoteWorkflow::factory()->create();
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => $company->id]);

    $definition->delete();

    $this->getJson("/api/quote-workflows/{$workflow->id}")->assertOk();
    $this->postJson('/api/tables/quote-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-037 — table `criteria_fields` column: i18n key for native, LITERAL
// label for custom (never an invented i18n key), raw field for D10.
// Discriminant the frontend cell relies on: only a
// 'quoteWorkflows.criterionFields.' prefix means "translate me".
// ---------------------------------------------------------------------------

it('table rows: criteria_fields emits the i18n key for a native criterion and the literal label for a custom one (AC-037)', function () {
    $definition = companyRelationCustomField();
    $company = Company::factory()->create();
    $source = Source::factory()->create();
    $actor = quoteWorkflowUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $workflow = QuoteWorkflow::factory()->create();
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => $company->id]);

    $response = $this->postJson('/api/tables/quote-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $workflow->id);

    expect($row['criteria_fields'])->toEqualCanonicalizing([
        'quoteWorkflows.criterionFields.source_id',
        'Azienda preferita',
    ]);
});

it('table rows: criteria_fields falls back to the raw field for a criterion whose custom definition is gone (D10/AC-037)', function () {
    $definition = companyRelationCustomField();
    $company = Company::factory()->create();
    $actor = quoteWorkflowUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $workflow = QuoteWorkflow::factory()->create();
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => $company->id]);

    $definition->delete();

    $response = $this->postJson('/api/tables/quote-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $workflow->id);

    expect($row['criteria_fields'])->toBe(["custom.{$definition->key}"]);
});
