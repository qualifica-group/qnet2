<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `quotes` authorization surface (spec 0065, MT-05): permissions:sync
 * (AC-060), 403 on every CRUD verb + the table/export endpoints (AC-061/062),
 * the DB field-permission matrix (AC-063), the `permissions.fields` block
 * (AC-064) and the navigation gate (AC-065).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteAuthUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteAuthUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-060 — permissions:sync creates all 8 quotes.* permissions
// ---------------------------------------------------------------------------

it('AC-060: permissions:sync creates all 8 quotes.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "quotes.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-061 — 403 without the permission, on every verb
// ---------------------------------------------------------------------------

it('AC-061: GET show is 403 without quotes.view', function () {
    $actor = quoteAuthUserWith([]);
    $target = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/quotes/{$target->id}")->assertForbidden();
});

it('AC-061: POST create is 403 without quotes.create, no row created', function () {
    $actor = quoteAuthUserWith([]);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', ['title' => 'Nope', 'opportunity_id' => $opportunity->id])->assertForbidden();

    expect(Quote::count())->toBe(0);
});

it('AC-061: PATCH update is 403 without quotes.update, no change persisted', function () {
    $actor = quoteAuthUserWith([]);
    $target = Quote::factory()->create(['title' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$target->id}", ['title' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('quotes', ['id' => $target->id, 'title' => 'Untouched']);
});

it('AC-061: DELETE destroy is 403 without quotes.delete, record still exists', function () {
    $actor = quoteAuthUserWith([]);
    $target = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quotes/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('quotes', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// AC-062 — 403 on the table columns endpoint and the export endpoint
// ---------------------------------------------------------------------------

it('AC-062: GET /api/tables/quotes/columns is 403 without quotes.viewAny', function () {
    $actor = quoteAuthUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/quotes/columns')->assertForbidden();
});

it('AC-062: POST /api/exports/quotes is 403 without quotes.export', function () {
    $actor = quoteAuthUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    // A well-formed payload (CreateExportRequest validates BEFORE the
    // controller's own authorizeExport() runs): the 403 must come from the
    // missing quotes.export ability, not from a 422 on the request shape.
    $this->postJson('/api/exports/quotes', [
        'format' => 'csv',
        'columns' => [['colId' => 'code', 'header' => 'Code']],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-063 — DB field-permission matrix (change-based enforcement)
// ---------------------------------------------------------------------------

it('AC-063: commercial_id editable:false for the actor\'s role -> 422 on a CHANGED value, no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $role = Role::create(['name' => 'quote-commercial-locked']);
    $role->givePermissionTo(['quotes.view', 'quotes.update']);
    $role->fieldPermissions()->create([
        'resource' => 'quotes',
        'field' => 'commercial_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $original = Referent::factory()->create();
    $target = Quote::factory()->create(['commercial_id' => $original->id]);
    Sanctum::actingAs($actor);

    $another = Referent::factory()->create();

    $this->patchJson("/api/quotes/{$target->id}", ['commercial_id' => $another->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_id');

    expect($target->fresh()->commercial_id)->toBe($original->id);
});

it('AC-063: PATCH resubmitting the SAME commercial_id for a locked field is a no-op that passes', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $role = Role::create(['name' => 'quote-commercial-locked-noop']);
    $role->givePermissionTo(['quotes.view', 'quotes.update']);
    $role->fieldPermissions()->create([
        'resource' => 'quotes',
        'field' => 'commercial_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $original = Referent::factory()->create();
    $target = Quote::factory()->create(['commercial_id' => $original->id, 'title' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$target->id}", ['commercial_id' => $original->id, 'title' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.commercial_id', $original->id)
        ->assertJsonPath('data.title', 'After');
});

// ---------------------------------------------------------------------------
// AC-064 — permissions.fields block: code readonly on show, editable on meta
// ---------------------------------------------------------------------------

it('AC-064: GET show exposes permissions.fields with the full key set, code readonly', function () {
    $actor = quoteAuthUserWith(['view', 'update']);
    $target = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/quotes/{$target->id}")->assertOk();
    $fields = $response->json('permissions.fields');

    // company_id/company_site_id/operational_site_id joined the field set with
    // the 2026-07-30 directive.
    expect(array_keys($fields))->toEqual([
        'code', 'title', 'opportunity_id', 'quote_status_id', 'commercial_id',
        'reporter_id', 'supervisor_id', 'company_id', 'company_site_id', 'operational_site_id',
        'internal_notes', 'offer_lines', 'cost_lines',
        'commissions', 'commission_recipient', 'commission_type', 'commission_value',
        'commission_internal_note',
    ])
        ->and($fields['code']['editable'])->toBeFalse()
        ->and($fields['code']['readonly'])->toBeTrue();
});

it('AC-064: GET /api/meta/quotes exposes code as editable and required', function () {
    $actor = quoteAuthUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/quotes')->assertOk();
    $fields = $response->json('permissions.fields');

    expect($fields['code']['editable'])->toBeTrue()
        ->and($fields['code']['required'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-065 — navigation gate
// ---------------------------------------------------------------------------

it('AC-065: the quotes navigation node only shows with quotes.view', function () {
    Permission::findOrCreate('quotes.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'opportunities-group'))
        ->not->toContain('quotes');

    $withView = User::factory()->create();
    $withView->givePermissionTo('quotes.view');
    Sanctum::actingAs($withView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'opportunities-group'))
        ->toContain('quotes');
});
