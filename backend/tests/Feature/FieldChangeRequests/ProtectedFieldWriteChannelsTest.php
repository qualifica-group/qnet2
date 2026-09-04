<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0078, F-4: the three real write channels for the protected
 * `source_id` field must all refuse a direct change from an actor without
 * `request-management.updateSource`, while the initial attribution at
 * creation (F-4a) stays free. Spec 0086, D-2: the write channels operate on
 * the Quote now — `source_id` itself still lives on the Opportunity.
 */
uses(RefreshDatabase::class);

if (! function_exists('sourceProtectedActor')) {
    /**
     * @param  array<int, string>  $extraAbilities
     */
    function sourceProtectedActor(array $extraAbilities = []): User
    {
        $abilities = ['viewAny', 'view', 'create', 'update', 'viewAll', 'assignOperator', 'updateSource'];

        foreach ($abilities as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.view', 'request-management.update']);
        $user->givePermissionTo($extraAbilities);

        return $user;
    }
}

if (! function_exists('quoteSupervisedBy')) {
    function quoteSupervisedBy(User $operator): Quote
    {
        $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
        $opportunity->managers()->sync([$operator->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-007 — work panel PATCH, source_id changed -> 422, DB untouched
// ---------------------------------------------------------------------------

it('AC-007: PATCH the work panel with a different source_id -> 422, value unchanged', function () {
    $actor = sourceProtectedActor();
    $quote = quoteSupervisedBy($actor);
    $originalSourceId = $quote->opportunity->source_id;
    $otherSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['source_id' => $otherSource->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('source_id');

    expect($quote->opportunity->fresh()->source_id)->toBe($originalSourceId);
});

// ---------------------------------------------------------------------------
// AC-008 — same PATCH, source_id UNCHANGED -> 200, other fields still saved
// ---------------------------------------------------------------------------

it('AC-008: PATCH the work panel with the SAME source_id -> 200, other fields saved (change-based)', function () {
    $actor = sourceProtectedActor();
    $quote = quoteSupervisedBy($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'source_id' => $quote->opportunity->source_id,
        'next_callback_at' => '2026-09-01',
    ])->assertOk();

    expect($quote->opportunity->fresh()->source_id)->toBe($quote->opportunity->source_id)
        ->and($quote->fresh()->next_callback_at?->toDateString())->toBe('2026-09-01');
});

// ---------------------------------------------------------------------------
// AC-009 — inline grid cell PATCH -> 403, value unchanged
// ---------------------------------------------------------------------------

it('AC-009: PATCH the inline "source" cell with a different value -> 403, value unchanged', function () {
    $actor = sourceProtectedActor();
    $quote = quoteSupervisedBy($actor);
    $originalSourceId = $quote->opportunity->source_id;
    $otherSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'source',
        'value' => $otherSource->id,
    ])->assertStatus(403);

    expect($quote->opportunity->fresh()->source_id)->toBe($originalSourceId);
});

// ---------------------------------------------------------------------------
// AC-010 — creation stays free (F-4a)
// ---------------------------------------------------------------------------

it('AC-010: POST /api/request-management (creation) sets the Fonte freely regardless of updateSource', function () {
    $actor = sourceProtectedActor(['request-management.create']);
    $registry = Registry::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
        'source_id' => $source->id,
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    $this->assertDatabaseHas('opportunities', [
        'id' => $quote->opportunity_id,
        'source_id' => $source->id,
    ]);
});

// ---------------------------------------------------------------------------
// AC-011 — an actor WITH updateSource succeeds on both channels
// ---------------------------------------------------------------------------

it('AC-011: an actor with updateSource can change the Fonte from the work panel', function () {
    $actor = sourceProtectedActor(['request-management.updateSource']);
    $quote = quoteSupervisedBy($actor);
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['source_id' => $newSource->id])
        ->assertOk();

    expect($quote->opportunity->fresh()->source_id)->toBe($newSource->id);
});

it('AC-011: an actor with updateSource can change the Fonte from the inline cell', function () {
    $actor = sourceProtectedActor(['request-management.updateSource']);
    $quote = quoteSupervisedBy($actor);
    $newSource = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'source',
        'value' => $newSource->id,
    ])->assertOk();

    expect($quote->opportunity->fresh()->source_id)->toBe($newSource->id);
});

// ---------------------------------------------------------------------------
// AC-012 — reassigning the request does not carry the permission along
// ---------------------------------------------------------------------------

it('AC-012: a request reassigned to a new operator without updateSource still 422s on the Fonte', function () {
    $manager = sourceProtectedActor(['request-management.viewAll', 'request-management.assignOperator']);
    $previousOperator = sourceProtectedActor();
    $newAssignee = sourceProtectedActor();
    $quote = quoteSupervisedBy($previousOperator);
    $site = OperationalSite::factory()->withAddress()->create();
    $otherSource = Source::factory()->create();

    Sanctum::actingAs($manager);
    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $newAssignee->id,
    ])->assertOk();

    Sanctum::actingAs($newAssignee);
    $this->patchJson("/api/request-management/{$quote->id}", ['source_id' => $otherSource->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('source_id');

    expect($quote->opportunity->fresh()->source_id)->not->toBe($otherSource->id);
});
