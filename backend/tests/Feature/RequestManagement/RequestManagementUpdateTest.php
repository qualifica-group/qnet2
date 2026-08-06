<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// PATCH /api/request-management/{opportunity} (spec 0049 data_contract,
// AC-030/031/032/040/041/042/043).

uses(RefreshDatabase::class);

if (! function_exists('requestManagementUpdaterWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementUpdaterWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('managedOpportunity')) {
    function managedOpportunity(User $manager): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// AC-030/031 (spec 0049) are GONE: spec 0083, D-2 removed the Opportunity's
// own working-state field entirely — the PATCH payload no longer accepts a
// working-state override at all. That rule now lives on the Offerta
// (tests/Feature/Quotes/QuoteRequiresNoteTest.php, AC-021/023..026).
//
// AC-032 — authz + scope + 404
// ---------------------------------------------------------------------------

it('PATCH without request-management.update -> 403 (AC-032)', function () {
    $actor = requestManagementUpdaterWith([]);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [])->assertForbidden();
});

it('PATCH on an opportunity the actor does not manage and without viewAll -> 403 (AC-032)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [])->assertForbidden();
});

it('PATCH on a nonexistent opportunity -> 404 (AC-032)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/request-management/999999', [])->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-040 / AC-041 / AC-042 — attribute_values write pipeline
// ---------------------------------------------------------------------------

if (! function_exists('opportunityWithApplicableAttribute')) {
    /**
     * @return array{opportunity: Opportunity, attribute: Attribute}
     */
    function opportunityWithApplicableAttribute(array $attributeOverrides = [], bool $required = false): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
        $attribute = Attribute::factory()->create($attributeOverrides);
        $category->attributes()->attach($attribute->id, ['is_required' => $required, 'sort_order' => 0]);

        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $category->id,
        ]);

        return ['opportunity' => $opportunity, 'attribute' => $attribute];
    }
}

it('PATCH with a valid attribute_values payload -> 200, merged into the stored map (AC-040)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    ['opportunity' => $opportunity, 'attribute' => $attribute] = opportunityWithApplicableAttribute([
        'code' => 'contract_length', 'type' => 'integer',
    ]);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'attribute_values' => ['contract_length' => '24'],
    ])->assertOk()->assertJsonPath('data.attribute_values.contract_length', 24);

    expect($opportunity->fresh()->attribute_values)->toBe(['contract_length' => 24]);
});

it('PATCH merges attribute_values, keeping previously stored codes untouched (AC-040)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    ['opportunity' => $opportunity, 'attribute' => $attribute] = opportunityWithApplicableAttribute([
        'code' => 'contract_length', 'type' => 'integer',
    ]);
    $opportunity->forceFill(['attribute_values' => ['contract_length' => 12]])->save();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    // A second applicable code, submitted alone: the first must survive the merge.
    $category = $opportunity->productLines()->first()->productCategory;
    $otherAttribute = Attribute::factory()->create(['code' => 'notes', 'type' => 'text']);
    $category->attributes()->attach($otherAttribute->id, ['is_required' => false, 'sort_order' => 1]);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'attribute_values' => ['notes' => 'hello'],
    ])->assertOk();

    expect($opportunity->fresh()->attribute_values)->toBe(['contract_length' => 12, 'notes' => 'hello']);
});

it('PATCH with a code not in the applicable set -> 422 keyed attribute_values.<code> (AC-041)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'attribute_values' => ['unknown_code' => 'x'],
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_values.unknown_code');
});

it('PATCH with a value not valid for the attribute type -> 422 (AC-041)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    ['opportunity' => $opportunity] = opportunityWithApplicableAttribute(['code' => 'quantity', 'type' => 'integer']);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'attribute_values' => ['quantity' => 'not-a-number'],
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_values.quantity');
});

it('PATCH with a required attribute submitted empty -> 422 (AC-042)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    ['opportunity' => $opportunity] = opportunityWithApplicableAttribute(['code' => 'mandatory_field', 'type' => 'text'], required: true);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'attribute_values' => ['mandatory_field' => ''],
    ])->assertStatus(422)->assertJsonValidationErrors('attribute_values.mandatory_field');
});

it('PATCH with a non-required attribute simply absent -> ok (AC-042)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    ['opportunity' => $opportunity] = opportunityWithApplicableAttribute(['code' => 'optional_field', 'type' => 'text'], required: false);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['attribute_values' => []])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-043 — operative changes are recorded on the Opportunity's activity log
// ---------------------------------------------------------------------------

it('PATCH next_callback_at + attribute_values writes an activity entry on the Opportunity (AC-043)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    ['opportunity' => $opportunity] = opportunityWithApplicableAttribute(['code' => 'contract_length', 'type' => 'integer']);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    $callbackAt = now()->addDay()->format('Y-m-d\TH:i');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => $callbackAt,
        'attribute_values' => ['contract_length' => 6],
    ])->assertOk();

    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($actor->id);
    expect($activity->properties->get('attributes'))->toHaveKey('next_callback_at')
        ->and($activity->properties->get('attributes'))->toMatchArray([
            'attribute_values' => ['contract_length' => 6],
        ]);

    // The module exposes no separately-gated activity endpoint (lead
    // decision): the generic ActivityLogController resolves its Policy by
    // MODEL CLASS, so a `request-management` resource key would have been
    // gated by `opportunities.*`, never `request-management.viewActivity` —
    // removed from config/activity-log.php. This write-side entry is
    // readable through the Opportunity's OWN resource key instead; see
    // RequestManagementActivityTest for the dedicated coverage.
});

it('PATCH with no actual change writes no activity entry', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $before = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'general_notes' => $opportunity->general_notes,
    ])->assertOk();

    $after = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    expect($after)->toBe($before);
});
