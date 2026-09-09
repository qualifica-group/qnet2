<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// User directive 2026-07-31 ("la create il piu' simile possibile al pannello"):
// the create form carries the operative fields the work panel edits — next
// callback and note generali. Spec 0083, D-2: the working status (+ its
// mandatory note) is GONE from this channel entirely. Spec 0084, D-1: the
// dynamic attribute values (+ the POST /api/request-management/form-context
// preview that used to render them) are GONE too — that concern moved to the
// Offerta (Quote), see tests/Feature/Quotes/QuoteAttributeValuesTest.php.

uses(RefreshDatabase::class);

if (! function_exists('requestCreateOperativeActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestCreateOperativeActor(array $abilities = ['create'], bool $canCreateNotes = false): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

if (! function_exists('requestCreateProductLine')) {
    /**
     * A valid {business_function_id, product_category_id} pair for the
     * mandatory `product_lines` row.
     *
     * @return array{business_function_id: int, product_category_id: int}
     */
    function requestCreateProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id];
    }
}

// ---------------------------------------------------------------------------
// POST /api/request-management/form-context (user directive 2026-08-07).
// REQUIREMENT CHANGE: spec 0084 D-1 had removed this endpoint along with the
// section it previews; the directive brings both back, resolving the
// "Informazioni aggiuntive" of the Offerta the create form is about to open.
// The former test (the endpoint 405s) asserted the superseded rule.
// ---------------------------------------------------------------------------

it('form-context: resolves the applicable set for the picked categories', function () {
    Sanctum::actingAs(requestCreateOperativeActor());
    $line = requestCreateProductLine();

    $this->postJson('/api/request-management/form-context', ['product_lines' => [$line]])
        ->assertOk()
        ->assertJsonStructure(['data' => ['applicable_attributes', 'attribute_layout']]);
});

it('form-context: an incomplete product line scopes nothing, never a 422', function () {
    Sanctum::actingAs(requestCreateOperativeActor());

    $response = $this->postJson('/api/request-management/form-context', [
        'product_lines' => [['business_function_id' => null, 'product_category_id' => null]],
    ])->assertOk();

    expect($response->json('data.applicable_attributes'))->toBe([]);
});

it('form-context: requires request-management.create', function () {
    Sanctum::actingAs(requestCreateOperativeActor(['view']));

    $this->postJson('/api/request-management/form-context', ['product_lines' => []])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// POST /api/request-management — the operative fields at creation
// ---------------------------------------------------------------------------

it('create: next_callback_at and general_notes are persisted and read back', function () {
    $actor = requestCreateOperativeActor();
    $line = requestCreateProductLine();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'next_callback_at' => '2026-09-01T10:30',
        'general_notes' => 'Il cliente richiama a settembre.',
    ])->assertCreated();

    expect($response->json('data.next_callback_at'))->toBe('2026-09-01T10:30');
    expect($response->json('data.general_notes'))->toBe('Il cliente richiama a settembre.');

    // The callback lands on the created Offerta (user directive 2026-09-04),
    // the general notes stay on the Opportunity.
    expect(Quote::query()->sole()->next_callback_at->format('Y-m-d\TH:i'))->toBe('2026-09-01T10:30')
        ->and(Opportunity::query()->sole()->general_notes)->toBe('Il cliente richiama a settembre.');
});

// REQUIREMENT CHANGE (user directive 2026-08-07): `attribute_values` used to
// be silently ignored on this channel (spec 0084, D-1). It is now written on
// the created Offerta, validated against the applicable set the inserted
// product lines resolve — the happy path lives in
// RequestManagementAttributeValuesTest, this one guards the rejection.
it('create: a code outside the applicable set -> 422, nothing is created', function () {
    $actor = requestCreateOperativeActor();
    $line = requestCreateProductLine();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'attribute_values' => ['material' => 'steel'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attribute_values.material');

    // The whole creation runs in ONE transaction: a rejected value rolls the
    // Opportunity and its Offerta back with it.
    expect(Opportunity::query()->count())->toBe(0);
});

it('create: none of the operative fields submitted leaves the record exactly as before', function () {
    $actor = requestCreateOperativeActor();
    $line = requestCreateProductLine();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
    ])->assertCreated();

    expect(Quote::query()->sole()->next_callback_at)->toBeNull()
        ->and(Opportunity::query()->sole()->general_notes)->toBeNull();
});
