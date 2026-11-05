<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0357 (user directive 2026-11-05): the Offerta's "Commerciale"
 * (`quotes.commercial_id`) is editable from Gestione richieste, next to the
 * Segnalatore, on both the create and the panel channel.
 */
uses(RefreshDatabase::class);

if (! function_exists('commercialFieldActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management ability names
     * @param  array<string, mixed>|null  $matrixRow  a single role_field_permissions row
     */
    function commercialFieldActor(array $abilities = ['viewAny', 'view', 'update', 'viewAll'], ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'commercial-field-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "request-management.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('commercialFieldCreatePayload')) {
    /**
     * The smallest valid POST body (registry branch + one product line + the
     * mandatory Fonte) — local to this file, so running it alone resolves.
     *
     * @return array<string, mixed>
     */
    function commercialFieldCreatePayload(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'registry_id' => Registry::factory()->create()->id,
            'source_id' => Source::factory()->create()->id,
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-001 — read
// ---------------------------------------------------------------------------

it('AC-001: GET exposes the Offerta commercial, not the Opportunity one', function () {
    $actor = commercialFieldActor();
    $quoteCommercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => Referent::factory()->create()->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'commercial_id' => $quoteCommercial->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.commercial_id', $quoteCommercial->id)
        ->assertJsonPath('data.commercial.id', $quoteCommercial->id)
        ->assertJsonPath('data.commercial.name', $quoteCommercial->name);
});

it('AC-001: GET reports a null commercial when the Offerta has none', function () {
    $actor = commercialFieldActor();
    $quote = Quote::factory()->create(['commercial_id' => null]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.commercial_id', null)
        ->assertJsonPath('data.commercial', null);
});

// ---------------------------------------------------------------------------
// AC-002 — panel write
// ---------------------------------------------------------------------------

it('AC-002: PATCH writes the commercial and returns it on the same response', function () {
    $actor = commercialFieldActor();
    $quote = Quote::factory()->create(['commercial_id' => null]);
    $commercial = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_id' => $commercial->id])
        ->assertOk()
        ->assertJsonPath('data.commercial_id', $commercial->id)
        ->assertJsonPath('data.commercial.name', $commercial->name);

    expect($quote->fresh()->commercial_id)->toBe($commercial->id);
});

it('AC-002: PATCH clears the commercial with null and leaves it untouched when the key is absent', function () {
    $actor = commercialFieldActor();
    $commercial = Referent::factory()->create();
    $quote = Quote::factory()->create(['commercial_id' => $commercial->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['next_callback_at' => '2026-11-10T09:00'])->assertOk();
    expect($quote->fresh()->commercial_id)->toBe($commercial->id);

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_id' => null])
        ->assertOk()
        ->assertJsonPath('data.commercial_id', null);

    expect($quote->fresh()->commercial_id)->toBeNull();
});

it('AC-002: PATCH rejects a commercial that is not an existing referent', function () {
    $actor = commercialFieldActor();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_id');
});

it('AC-002: the commercial change is logged on the OPPORTUNITY thread', function () {
    $actor = commercialFieldActor();
    $previous = Referent::factory()->create();
    $quote = Quote::factory()->create(['commercial_id' => $previous->id]);
    $commercial = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_id' => $commercial->id])->assertOk();

    $properties = Activity::query()
        ->where('subject_type', $quote->opportunity->getMorphClass())
        ->where('subject_id', $quote->opportunity_id)
        ->where('event', 'updated')
        ->sole()
        ->properties;

    expect($properties->get('attributes'))->toMatchArray(['commercial_id' => $commercial->id])
        ->and($properties->get('old'))->toMatchArray(['commercial_id' => $previous->id]);
});

// ---------------------------------------------------------------------------
// AC-003 — per-field gating
// ---------------------------------------------------------------------------

it('AC-003: a role with commercial_id non-editable gets a 422 on a real change', function () {
    $actor = commercialFieldActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'commercial_id', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $commercial = Referent::factory()->create();
    $quote = Quote::factory()->create(['commercial_id' => $commercial->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_id' => Referent::factory()->create()->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_id');

    expect($quote->fresh()->commercial_id)->toBe($commercial->id);
});

it('AC-003: the panel envelope carries the field permission', function () {
    $actor = commercialFieldActor();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/request-management/{$quote->id}")->assertOk()->json('permissions.fields');

    expect($fields['commercial_id']['visible'])->toBeTrue()
        ->and($fields['commercial_id']['editable'])->toBeTrue()
        ->and($fields['commercial_id']['required'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-004 — creation
// ---------------------------------------------------------------------------

it('AC-004: POST stores the commercial on both the Offerta and the Opportunity', function () {
    $actor = commercialFieldActor(['viewAny', 'view', 'create', 'update', 'viewAll']);
    $commercial = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        ...commercialFieldCreatePayload(),
        'commercial_id' => $commercial->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.commercial_id', $commercial->id)
        ->assertJsonPath('data.commercial.name', $commercial->name);

    $quote = Quote::with('opportunity')->findOrFail($response->json('data.id'));

    expect($quote->commercial_id)->toBe($commercial->id)
        ->and($quote->opportunity->commercial_id)->toBe($commercial->id);
});

it('AC-004: POST without the key leaves both commercials empty', function () {
    $actor = commercialFieldActor(['viewAny', 'view', 'create', 'update', 'viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', commercialFieldCreatePayload())
        ->assertCreated()
        ->assertJsonPath('data.commercial_id', null);

    $quote = Quote::with('opportunity')->findOrFail($response->json('data.id'));

    expect($quote->commercial_id)->toBeNull()
        ->and($quote->opportunity->commercial_id)->toBeNull();
});

it('AC-004: POST rejects a commercial that is not an existing referent', function () {
    $actor = commercialFieldActor(['viewAny', 'view', 'create', 'update', 'viewAll']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        ...commercialFieldCreatePayload(),
        'commercial_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_id');
});
