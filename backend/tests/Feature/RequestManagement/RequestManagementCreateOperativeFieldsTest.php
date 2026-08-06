<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
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
// POST /api/request-management/form-context is REMOVED (spec 0084, D-1): the
// live preview it served no longer applies once the dynamic section moved to
// the Offerta. The literal route is gone; `form-context` now only matches the
// `{opportunity}` wildcard's OTHER verbs (GET/PUT/PATCH/DELETE), so POST 405s
// rather than 404s.
// ---------------------------------------------------------------------------

it('form-context: the removed endpoint no longer resolves', function () {
    Sanctum::actingAs(requestCreateOperativeActor());

    $this->postJson('/api/request-management/form-context', ['product_lines' => []])
        ->assertStatus(405);
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
    expect($response->json('data.context.general_notes'))->toBe('Il cliente richiama a settembre.');

    $opportunity = Opportunity::query()->sole();
    expect($opportunity->next_callback_at->format('Y-m-d\TH:i'))->toBe('2026-09-01T10:30')
        ->and($opportunity->general_notes)->toBe('Il cliente richiama a settembre.');
});

it('create: an attribute_values payload is silently ignored (spec 0084, D-1)', function () {
    $actor = requestCreateOperativeActor();
    $line = requestCreateProductLine();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [$line],
        'attribute_values' => ['material' => 'steel'],
    ])->assertCreated();

    expect($response->json('data'))->not->toHaveKey('attribute_values');
    expect(Opportunity::query()->sole()->getAttributes())->not->toHaveKey('attribute_values');
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

    $opportunity = Opportunity::query()->sole();
    expect($opportunity->next_callback_at)->toBeNull()
        ->and($opportunity->general_notes)->toBeNull();
});
