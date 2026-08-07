<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management (spec 0057, migrated onto the Quote by spec
// 0086 D-5): creates the Opportunity AND the Offerta behind a new "Gestione
// Richieste" row in one transaction, gated by `request-management.create` —
// AC-001..AC-007 (permission gate, the D-2 registry/client_identity XOR,
// product_lines validation, name derivation). The attribution block
// (Fonte/Segnalatore/rewards/Operatore/Sede) and AC-010 live in
// RequestManagementCreateAttributionTest (engineering.md §6, file-size
// budget). `data.id` is the OFFERTA (Quote) id (AC-027), never the
// Opportunity's — fixtures that need both seed a decoy Opportunity first so
// the two ids can never coincide by construction (a coincidence would let an
// assertion that confuses the two records pass for the wrong reason).

uses(RefreshDatabase::class);

if (! function_exists('requestManagementCreatorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementCreatorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'export', 'viewActivity', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('aSourceId')) {
    /** The Fonte every successful create must carry: mandatory since the user directive 2026-07-29. */
    function aSourceId(): int
    {
        return Source::factory()->create()->id;
    }
}

if (! function_exists('oneProductLine')) {
    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    function oneProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]];
    }
}

if (! function_exists('decoyOpportunity')) {
    /**
     * A throwaway Opportunity, created before the real POST under test so its
     * id can never coincide with the freshly-created Offerta's own id.
     */
    function decoyOpportunity(): void
    {
        Opportunity::factory()->create();
    }
}

// ---------------------------------------------------------------------------
// AC-001 — 403 without the permission
// ---------------------------------------------------------------------------

it('AC-001: POST without request-management.create -> 403, no row created', function () {
    $actor = requestManagementCreatorWith([]);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertForbidden();

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-002 — existing registry branch
// ---------------------------------------------------------------------------

it('AC-002: POST with registry_id + product_lines -> 201, attached to that registry, its anagrafica untouched', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->withPersonalData()->create();
    $originalName = $registry->name;
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    expect($quote->opportunity_id)->not->toBe($quote->id);
    $this->assertDatabaseHas('opportunities', ['id' => $quote->opportunity_id, 'registry_id' => $registry->id]);
    expect($registry->fresh()->name)->toBe($originalName);
});

// ---------------------------------------------------------------------------
// AC-003 — new client branch (identity + contacts + address)
// ---------------------------------------------------------------------------

it('AC-003: POST with client_identity + contacts + address -> 201, new Registry+PersonalData created, name derived', function () {
    $actor = requestManagementCreatorWith(['create']);
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'client_identity' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ],
        'client_contacts' => [
            ['type' => 'phone', 'value' => '3331234567', 'is_primary' => true],
        ],
        'client_address' => ['line1' => 'Via Roma 1'],
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    $opportunity = Opportunity::with('registry.personalData.contacts', 'registry.personalData.addresses')->findOrFail($quote->opportunity_id);
    $registry = $opportunity->registry;

    expect($registry)->not->toBeNull();
    $card = $registry->personalData;
    expect($card)->not->toBeNull();
    expect($card->first_name)->toBe('Mario');
    expect($card->last_name)->toBe('Rossi');
    expect($card->contacts)->toHaveCount(1);
    expect($card->contacts->first()->value)->toBe('3331234567');
    expect($card->addresses)->toHaveCount(1);
    expect($card->addresses->first()->line1)->toBe('Via Roma 1');
    // registries.name is derived from the card (RegistryProfileWriter), same
    // precedent as POST /api/registries.
    expect($registry->name)->toBe($card->fresh()->full_name);
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — the D-2 XOR
// ---------------------------------------------------------------------------

it('AC-004: POST with registry_id AND client_identity together -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'client_identity' => ['type' => 'individual', 'first_name' => 'Mario', 'last_name' => 'Rossi'],
        'product_lines' => oneProductLine(),
    ])->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

it('AC-005: POST with neither registry_id nor client_identity -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'product_lines' => oneProductLine(),
    ])->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-006 — product_lines validation
// ---------------------------------------------------------------------------

it('AC-006: POST without product_lines -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', ['registry_id' => $registry->id])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

it('AC-006: POST with a category not belonging to the chosen business function -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $otherBusinessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $otherBusinessFunction->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.business_function_id');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-007 — the Opportunity's name is derived
// ---------------------------------------------------------------------------

it('AC-007: the created opportunity name is OPP_{id}, and the response id is the OFFERTA, not the Opportunity', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    $opportunity = $quote->opportunity;
    expect($quote->opportunity_id)->not->toBe($quote->id);
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id);
    expect($response->json('data.name'))->toBe('OPP_'.$opportunity->id);
});

// ---------------------------------------------------------------------------
// AC-030 — a failure INSIDE the transaction, after the Opportunity is already
// persisted, must roll back BOTH records. Every other "no row created" case
// in this file 422s at the FormRequest layer, before RequestCreationService's
// own DB::transaction() ever opens (RequestCreationService.php:60) — that
// proves "never attempted", not "rolled back mid-flight". This forces a
// failure past validation, inside the transaction, by making the Offerta's
// own creation step throw.
// ---------------------------------------------------------------------------

it('AC-030: a failure inside the transaction, after the Opportunity insert, leaves neither record persisted', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $this->mock(QuoteService::class, function ($mock): void {
        $mock->shouldReceive('create')
            ->once()
            ->andThrow(new RuntimeException('Simulated failure after the Opportunity insert'));
    });

    $opportunitiesBefore = Opportunity::count();

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertStatus(500);

    expect(Opportunity::count())->toBe($opportunitiesBefore);
    expect(Quote::count())->toBe(0);
});
