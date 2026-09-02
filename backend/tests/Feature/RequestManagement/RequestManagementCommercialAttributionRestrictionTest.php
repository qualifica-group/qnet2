<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * User directive 2026-08-03: the Commercial role neither SEES nor writes the
 * "Sede operativa" (`operational_site_id`) and the GA2 "Operatore" of a
 * request — attribution is decided FOR them. Supervisor and Marketing are
 * untouched. Spec 0086, D-2/D-3: the record is now a `quotes` row; both
 * fields live there (the operator on `quotes.operator_id`, spec 0087 D-9 —
 * no longer `quotes.supervisor_id`).
 *
 * Spec 0097, D-3: the operator's field key is now `manager_slots` — the panel
 * edits the whole team, the operator is its slot 2 — so the restriction is
 * seeded and asserted on THAT key. Nothing else about the rule changes: the
 * same three channels stay closed, the grid's `operator_ga2` cell included
 * (its `editableField` moved to the same new key).
 *
 * The restriction is seeded by TestUsersSeeder (the role matrix is the source
 * of truth, not a hard-coded rule), so it is exercised against the real seeded
 * roles. Three layers close it, one per channel:
 *   - the `role_field_permissions` matrix — the work panel's read envelope,
 *     its PATCH, and the grid's inline cell edit;
 *   - `request-management.assignOperator` — the create form's Operatore and
 *     the bulk assign endpoint;
 *   - `operational-sites.viewAny` — the field ceiling, and creation.
 */
uses(RefreshDatabase::class);

if (! function_exists('restrictedCommercial')) {
    function restrictedCommercial(): User
    {
        return User::query()->where('email', 'campania@commerciale.com')->firstOrFail();
    }
}

if (! function_exists('requestOperatedBy')) {
    function requestOperatedBy(User $operator): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

it('hides both fields from the commercial work panel envelope', function () {
    $this->seed(TestUsersSeeder::class);

    $actor = restrictedCommercial();
    $quote = requestOperatedBy($actor);
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->json('permissions.fields');

    foreach (['operational_site_id', 'manager_slots'] as $field) {
        expect($fields[$field]['visible'])->toBeFalse($field)
            ->and($fields[$field]['hidden'])->toBeTrue($field)
            ->and($fields[$field]['editable'])->toBeFalse($field);
    }

    // The rest of the attribution block is untouched by the restriction.
    expect($fields['source_id']['visible'])->toBeTrue()
        ->and($fields['reporter_id']['editable'])->toBeTrue();
});

it('leaves both fields visible and editable for the supervisor', function () {
    $this->seed(TestUsersSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();
    Sanctum::actingAs($supervisor);
    $quote = Quote::factory()->create();

    $fields = $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->json('permissions.fields');

    foreach (['operational_site_id', 'manager_slots'] as $field) {
        expect($fields[$field]['visible'])->toBeTrue($field)
            ->and($fields[$field]['editable'])->toBeTrue($field);
    }
});

it('rejects the commercial PATCH of either field with a 422', function () {
    $this->seed(TestUsersSeeder::class);

    $actor = restrictedCommercial();
    $quote = requestOperatedBy($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    // A non-editable field whose value actually CHANGES is a validation error
    // (EnforcesFieldPermissions), never a silent no-op.
    $this->patchJson("/api/request-management/{$quote->id}", ['operational_site_id' => $site->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['operational_site_id']);

    // Spec 0097, AC-004: the team editor is the channel now — a genuine
    // change of the OPERATOR slot on a locked `manager_slots`.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, User::factory()->create()->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['manager_slots']);

    $quote->refresh();
    expect($quote->operational_site_id)->toBeNull()
        ->and($quote->operator_id)->toBe($actor->id);
});

it('refuses the commercial bulk assignment of Sede and Operatore', function () {
    $this->seed(TestUsersSeeder::class);

    $actor = restrictedCommercial();
    $quote = requestOperatedBy($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $actor->id,
    ])->assertForbidden();

    expect($quote->fresh()->operational_site_id)->toBeNull();
});

it('refuses either field on the commercial create, the one channel the matrix cannot reach', function () {
    $this->seed(TestUsersSeeder::class);

    $actor = restrictedCommercial();
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $businessFunction = BusinessFunction::factory()->create();
    $payload = [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        // Non-empty on purpose: `product_lines` has a `min:1` rule, and a 422
        // from the FormRequest would fire BEFORE the controller guard under
        // test (validation precedes the action).
        'product_lines' => [[
            'business_function_id' => $businessFunction->id,
            'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
        ]],
    ];

    $this->postJson('/api/request-management', [...$payload, 'operational_site_id' => $site->id])
        ->assertForbidden();

    $this->postJson('/api/request-management', [...$payload, 'manager_slots' => [null, $actor->id]])
        ->assertForbidden();

    expect(Opportunity::count())->toBe(0);
});

it('keeps the grid cells of both fields non-editable for the commercial', function () {
    $this->seed(TestUsersSeeder::class);

    $actor = restrictedCommercial();
    $quote = requestOperatedBy($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['operational_site']['editable'])->toBeFalse()
        ->and($columns['operator_ga2']['editable'])->toBeFalse();

    // The endpoint is the authority, not the flag above: TableCellUpdateService
    // re-derives the field permission against the real row and refuses.
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operational_site',
        'value' => $site->id,
    ])->assertForbidden();

    expect($quote->fresh()->operational_site_id)->toBeNull();
});
