<?php

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `report_columns` (spec 0141): per-category selection of the Gestione
 * Richieste / Iscritti report indicator columns, replacing the hardcoded
 * `config('request-management-report.category_columns')` name map (spec
 * 0131 D-4-bis). Null = inherit the nearest ancestor's (structural walk,
 * same shape as `is_reportable`).
 */
uses(RefreshDatabase::class);

if (! function_exists('reportColumnsActorWith')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. `product-categories.create`
     */
    function reportColumnsActorWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — GET /product-categories/report-columns
// ---------------------------------------------------------------------------

it('AC-001: returns the catalog columns in order, with translated labels', function (): void {
    Sanctum::actingAs(reportColumnsActorWith(['product-categories.viewAny']));
    app()->setLocale('it'); // resolves $expected below; the HTTP request's own locale is set via the Accept-Language header.

    $expected = array_map(
        static fn (string $key): array => ['key' => $key, 'label' => __("request-management-report.headers.{$key}")],
        (array) config('request-management-report.indicator_columns'),
    );

    $this->withHeader('Accept-Language', 'it')
        ->getJson('/api/product-categories/report-columns')
        ->assertOk()
        ->assertJsonPath('data', $expected);
});

it('AC-001: 403s without product-categories.viewAny', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/product-categories/report-columns')->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-002 — write-side validation and normalization
// ---------------------------------------------------------------------------

it('AC-002: rejects duplicate keys with 422', function (): void {
    Sanctum::actingAs(reportColumnsActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Duplicate',
        'report_columns' => ['richiami', 'telefonate', 'richiami'],
    ])->assertUnprocessable()->assertJsonValidationErrors('report_columns.2');
});

it('AC-002: saves a valid selection reordered to the catalog order', function (): void {
    Sanctum::actingAs(reportColumnsActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Reordered',
        'report_columns' => ['richiami', 'telefonate'],
    ])->assertCreated()->assertJsonPath('data.report_columns', ['telefonate', 'richiami']);
});

it('AC-002: rejects a key not in the indicator catalog with 422', function (): void {
    Sanctum::actingAs(reportColumnsActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Bad key',
        'report_columns' => ['not_a_real_column'],
    ])->assertUnprocessable()->assertJsonValidationErrors('report_columns.0');
});

it('AC-002: an empty array saves as null (inherit)', function (): void {
    $actor = reportColumnsActorWith(['product-categories.create', 'product-categories.update']);
    Sanctum::actingAs($actor);

    $id = $this->postJson('/api/product-categories', ['name' => 'Configured', 'report_columns' => ['telefonate']])
        ->assertCreated()->json('data.id');

    $this->patchJson("/api/product-categories/{$id}", ['report_columns' => []])
        ->assertOk()
        ->assertJsonPath('data.report_columns', null);

    expect(ProductCategory::find($id)->report_columns)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-003 — effective/source resolution on show
// ---------------------------------------------------------------------------

it('AC-003: a subcategory with no own selection inherits its configured ancestor, with the source category', function (): void {
    $actor = reportColumnsActorWith(['product-categories.create', 'product-categories.view', 'product-categories.update']);
    Sanctum::actingAs($actor);

    $parentId = $this->postJson('/api/product-categories', ['name' => 'Parent', 'report_columns' => ['telefonate', 'richiami']])
        ->assertCreated()->json('data.id');

    $childId = $this->postJson('/api/product-categories', ['name' => 'Child', 'parent_id' => $parentId])
        ->assertCreated()
        ->assertJsonPath('data.report_columns', null)
        ->assertJsonPath('data.effective_report_columns', ['telefonate', 'richiami'])
        ->assertJsonPath('data.report_columns_source_category', ['id' => $parentId, 'name' => 'Parent'])
        ->json('data.id');

    $this->getJson("/api/product-categories/{$childId}")
        ->assertOk()
        ->assertJsonPath('data.effective_report_columns', ['telefonate', 'richiami'])
        ->assertJsonPath('data.report_columns_source_category', ['id' => $parentId, 'name' => 'Parent']);

    // Its own selection wins, and the source disappears.
    $this->patchJson("/api/product-categories/{$childId}", ['report_columns' => ['potenziali']])
        ->assertOk()
        ->assertJsonPath('data.effective_report_columns', ['potenziali'])
        ->assertJsonPath('data.report_columns_source_category', null);
});

it('AC-003: no configured ancestor at all resolves to an empty effective selection', function (): void {
    $actor = reportColumnsActorWith(['product-categories.create', 'product-categories.view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', ['name' => 'Root, unconfigured'])
        ->assertCreated()
        ->assertJsonPath('data.report_columns', null)
        ->assertJsonPath('data.effective_report_columns', [])
        ->assertJsonPath('data.report_columns_source_category', null);
});

// ---------------------------------------------------------------------------
// rev-1 (2026-09-18) — inherited_report_columns: ancestors-only resolution,
// independent of the category's own `report_columns` (AC-009's own bug fix:
// "Torna alle ereditate" must not appear when there is nothing to inherit).
// ---------------------------------------------------------------------------

it('rev-1: a category with its own columns under an unconfigured parent has no inherited columns to fall back to', function (): void {
    $actor = reportColumnsActorWith(['product-categories.create', 'product-categories.view']);
    Sanctum::actingAs($actor);

    $parentId = $this->postJson('/api/product-categories', ['name' => 'Formazione'])
        ->assertCreated()->json('data.id');

    $this->postJson('/api/product-categories', [
        'name' => 'GOL',
        'parent_id' => $parentId,
        'report_columns' => ['telefonate', 'richiami'],
    ])->assertCreated()
        ->assertJsonPath('data.report_columns', ['telefonate', 'richiami'])
        ->assertJsonPath('data.effective_report_columns', ['telefonate', 'richiami'])
        ->assertJsonPath('data.inherited_report_columns', [])
        ->assertJsonPath('data.inherited_report_columns_source_category', null);
});

it('rev-1: a child with its own columns under a configured parent still sees what it would inherit', function (): void {
    $actor = reportColumnsActorWith(['product-categories.create', 'product-categories.view']);
    Sanctum::actingAs($actor);

    $parentId = $this->postJson('/api/product-categories', ['name' => 'Parent', 'report_columns' => ['telefonate', 'richiami']])
        ->assertCreated()->json('data.id');

    $childId = $this->postJson('/api/product-categories', [
        'name' => 'Child',
        'parent_id' => $parentId,
        'report_columns' => ['potenziali'],
    ])->assertCreated()
        ->assertJsonPath('data.report_columns', ['potenziali'])
        ->assertJsonPath('data.effective_report_columns', ['potenziali']) // own wins
        ->assertJsonPath('data.inherited_report_columns', ['telefonate', 'richiami']) // what it would fall back to
        ->assertJsonPath('data.inherited_report_columns_source_category', ['id' => $parentId, 'name' => 'Parent'])
        ->json('data.id');

    $this->getJson("/api/product-categories/{$childId}")
        ->assertOk()
        ->assertJsonPath('data.inherited_report_columns', ['telefonate', 'richiami'])
        ->assertJsonPath('data.inherited_report_columns_source_category', ['id' => $parentId, 'name' => 'Parent']);
});

// ---------------------------------------------------------------------------
// AC-007 — migration backfill + rollback
// ---------------------------------------------------------------------------

it('AC-007: the migration backfills the D-7 snapshot by name (case-insensitive/trimmed), rollback drops the column', function (): void {
    $migration = require database_path('migrations/2026_09_18_110000_add_report_columns_to_product_categories_table.php');

    $migration->down();
    expect(Schema::hasColumn('product_categories', 'report_columns'))->toBeFalse();

    $gol = ProductCategory::factory()->create(['name' => ' GOL ']);
    $apl = ProductCategory::factory()->create(['name' => 'apl']);
    $unmapped = ProductCategory::factory()->create(['name' => 'Varie']);

    $migration->up();

    expect(Schema::hasColumn('product_categories', 'report_columns'))->toBeTrue()
        ->and($gol->fresh()->report_columns)->toBe(['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'])
        ->and($apl->fresh()->report_columns)->toBe(['telefonate', 'richiami', 'nuovi_contatti', 'invio_presa_in_carico'])
        ->and($unmapped->fresh()->report_columns)->toBeNull();

    $migration->down();
    expect(Schema::hasColumn('product_categories', 'report_columns'))->toBeFalse();

    // Leave the schema as RefreshDatabase's next test expects it.
    $migration->up();
});

// ---------------------------------------------------------------------------
// AC-008 — QualificaCatalogSeeder backfill
// ---------------------------------------------------------------------------

// Spec 0159 D-7 supersedes the seeded map: range-free columns on, range-bound ones left unselected.
it('AC-008: seeds the spec 0159 D-7 report columns on the production categories, never overwriting an existing selection, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $columnsOf = static fn (string $name): ?array => ProductCategory::query()->where('name', $name)->value('report_columns');

    expect($columnsOf('GOL'))->toBe(['aule_gestione', 'aule_partenza', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'])
        ->and($columnsOf('Consulenza'))->toBe(['presa_appuntamenti', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'])
        ->and($columnsOf('APL'))->toBe(['unhandled_callbacks', 'unhandled_new_contacts'])
        ->and($columnsOf('Formazione'))->toBeNull(); // not in D-7, left unconfigured

    // An admin's own edit survives a re-seed.
    ProductCategory::query()->where('name', 'GOL')->update(['report_columns' => json_encode(['telefonate'])]);

    test()->seed(QualificaCatalogSeeder::class);

    expect($columnsOf('GOL'))->toBe(['telefonate'])
        ->and($columnsOf('Consulenza'))->toBe(['presa_appuntamenti', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials']);
});
