<?php

use App\Jobs\GenerateExportJob;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// AC-057 (guard preexisting) is proven generically, for EVERY domain in
// config/tables.php, by tests/Feature/Table/InlineCellEditingGuardTest.php —
// not duplicated here.

if (! function_exists('paymentMethodUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function paymentMethodUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("payment-methods.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("payment-methods.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-050 — columns config, frozen order + flags
// ---------------------------------------------------------------------------

it('GET /api/tables/payment-methods/columns: 403 without viewAny, 200 with the 8 frozen columns (AC-050)', function () {
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/payment-methods/columns')->assertForbidden();

    $actor = paymentMethodUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/payment-methods/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('payment-methods')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']])
        // `searchable` is a top-level allow-list of column ids (spec 0009),
        // not a per-column flag: no `searchable` key is ever emitted inside
        // an individual resolved column.
        ->and($data['searchable'])->toBe(['name', 'code']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'code', 'description', 'payment_days', 'sort_order', 'is_active', 'created_at', 'updated_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['code']['sortable'])->toBeTrue()
        ->and($columns['description']['sortable'])->toBeFalse()
        ->and($columns['description']['filterable'])->toBeTrue()
        ->and($columns['payment_days']['filterType'])->toBe('number')
        ->and($columns['is_active']['type'])->toBe('boolean');
});

it('columns: is_active resolves editable:true only for an actor holding payment-methods.update, every other column stays false (AC-050, D-4)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/payment-methods/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['is_active']['editable'])->toBeTrue();

    // No other column is editable (spec 0068 D-4: is_active is the ONLY one).
    foreach ($columns->except('is_active') as $id => $column) {
        expect($column['editable'] ?? false)->toBeFalse("column {$id} unexpectedly editable");
    }
});

// ---------------------------------------------------------------------------
// AC-051 — global search on name OR code
// ---------------------------------------------------------------------------

it('rows: search on a term present only in `code` finds the row, likewise for `name` (AC-051)', function () {
    // Codes without an underscore in this test on purpose: SQLite's LIKE (the
    // test DB) has no ESCAPE clause configured, so FilterApplier::escapeLike()'s
    // backslash-escaped `\_` is matched literally instead of as "escaped
    // underscore" — a pre-existing quirk of the shared search engine under
    // SQLite (MySQL, the production driver, honours the backslash escape by
    // default), unrelated to this domain.
    $actor = paymentMethodUserWith(['viewAny']);
    PaymentMethod::factory()->create(['name' => 'Alpha Method', 'code' => 'uniquecodezz']);
    PaymentMethod::factory()->create(['name' => 'Beta Method', 'code' => 'othercode']);
    Sanctum::actingAs($actor);

    $byCode = $this->postJson('/api/tables/payment-methods/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'uniquecodezz'])->assertOk();
    expect(collect($byCode->json('items'))->pluck('code')->all())->toBe(['uniquecodezz']);

    $byName = $this->postJson('/api/tables/payment-methods/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'Alpha Method'])->assertOk();
    expect(collect($byName->json('items'))->pluck('name')->all())->toBe(['Alpha Method']);

    $none = $this->postJson('/api/tables/payment-methods/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'nonexistent-term'])->assertOk();
    expect($none->json('items'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-052 — is_active filter
// ---------------------------------------------------------------------------

it('rows: filter on is_active returns only matching rows (AC-052)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    PaymentMethod::factory()->create(['name' => 'Active Row', 'is_active' => true]);
    PaymentMethod::factory()->create(['name' => 'Inactive Row', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/payment-methods/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'filter' => false]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toContain('Inactive Row')->and($names)->not->toContain('Active Row');
});

// ---------------------------------------------------------------------------
// AC-053 — sort_order/name sort, defaults
// ---------------------------------------------------------------------------

it('rows: sorts by sort_order and name asc/desc, defaults to sort_order asc + limit 25 (AC-053)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    PaymentMethod::factory()->create(['name' => 'Zeta method', 'sort_order' => 100]);
    PaymentMethod::factory()->create(['name' => 'Alpha method', 'sort_order' => 101]);
    Sanctum::actingAs($actor);

    $bySortOrder = $this->postJson('/api/tables/payment-methods/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'sort_order', 'sort' => 'desc']],
    ])->assertOk();
    $names = collect($bySortOrder->json('items'))->pluck('name')->all();
    expect(array_search('Alpha method', $names, true))->toBeLessThan(array_search('Zeta method', $names, true));

    $byName = $this->postJson('/api/tables/payment-methods/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'name', 'sort' => 'asc']],
    ])->assertOk();
    $namesAsc = collect($byName->json('items'))->pluck('name')->all();
    expect(array_search('Alpha method', $namesAsc, true))->toBeLessThan(array_search('Zeta method', $namesAsc, true));

    PaymentMethod::factory()->count(28)->create();
    $default = $this->postJson('/api/tables/payment-methods/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    expect($default->json('pagination.total'))->toBe(30)
        ->and($default->json('items'))->toHaveCount(25);
});

// ---------------------------------------------------------------------------
// AC-054 — row actions gated per permission, delete carries confirm/danger
// ---------------------------------------------------------------------------

it('rows: view/edit/delete/activity actions present only with the matching permission (AC-054)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']);
    PaymentMethod::factory()->create(['name' => 'Full Actions']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/payment-methods/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Full Actions');

    expect($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete', 'activity']);
});

it('rows: an actor with only viewAny sees no row actions (AC-054)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    PaymentMethod::factory()->create(['name' => 'No Actions']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/payment-methods/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'No Actions');

    expect($row['actions'])->toBe([]);
});

it('columns: the delete action config carries confirm=true and type=danger (AC-054)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'delete']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/payment-methods/columns')->assertOk()->json('data');
    $deleteAction = collect($data['actions'])->firstWhere('key', 'delete');

    expect($deleteAction)->not->toBeNull()
        ->and($deleteAction['confirm'])->toBeTrue()
        ->and($deleteAction['type'])->toBe('danger');
});

// ---------------------------------------------------------------------------
// AC-055 — export "for free" via config/tables.php registration
// ---------------------------------------------------------------------------

it('export: payment-methods is registered in the generic export engine, no dedicated code (AC-055)', function () {
    Queue::fake();
    $actor = paymentMethodUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/payment-methods', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'payment-methods');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without payment-methods.export, no export job pushed (AC-055)', function () {
    Queue::fake();
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/payment-methods', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

// ---------------------------------------------------------------------------
// AC-056 — advanced filters: is_active (Switch), payment_days (NumberRange)
// ---------------------------------------------------------------------------

it('advanced filters: is_active (Switch) and payment_days (NumberRange) are exposed and filter correctly (AC-056)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    PaymentMethod::factory()->create(['name' => 'Short Terms', 'payment_days' => 5, 'is_active' => true]);
    PaymentMethod::factory()->create(['name' => 'Long Terms', 'payment_days' => 60, 'is_active' => true]);
    Sanctum::actingAs($actor);

    $columns = $this->getJson('/api/tables/payment-methods/columns')->assertOk()->json('data');
    $filters = collect($columns['advancedFilters'])->keyBy('name');

    expect($filters['is_active']['type'])->toBe('switch')
        ->and($filters['payment_days_range']['type'])->toBe('number_range');

    $response = $this->postJson('/api/tables/payment-methods/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['payment_days_range' => ['from' => 0, 'to' => 10]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toContain('Short Terms')->and($names)->not->toContain('Long Terms');
});
