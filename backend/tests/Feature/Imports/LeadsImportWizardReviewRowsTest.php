<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadsImportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadsImportActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

/**
 * A `reviewing` run with 3 staged rows, ordered by row_number: a `b`-email
 * warning row, an `a`-email valid row, and a `c`-email error row — deliberately
 * out of alphabetical order so a real `email`-column sort is observable.
 */
function reviewingLeadsRun(User $actor): ImportRun
{
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => ['Email' => 'email', 'Nome' => 'first_name', 'Cognome' => 'last_name'],
    ]);

    ImportRunRow::factory()->create([
        'import_run_id' => $run->id, 'row_number' => 1,
        'mapped_values' => ['email' => 'b@example.com', 'first_name' => 'Bruno'],
    ]);
    ImportRunRow::factory()->create([
        'import_run_id' => $run->id, 'row_number' => 2,
        'mapped_values' => ['email' => 'a@example.com', 'first_name' => 'Anna'],
    ]);
    ImportRunRow::factory()->error()->create([
        'import_run_id' => $run->id, 'row_number' => 3,
        'mapped_values' => ['email' => 'c@example.com', 'first_name' => 'Carlo'],
    ]);

    return $run;
}

// ---------------------------------------------------------------------------
// AC-016 — SSRM allow-list: sort/filter/search
// ---------------------------------------------------------------------------

it('AC-016: sorts by the real `row_number` column', function () {
    $actor = leadsImportActorWith(['import']);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        'sortModel' => [['colId' => 'row_number', 'sort' => 'desc']],
    ])->assertOk();

    expect($response->json('items.0.row_number'))->toBe(3);
});

it('AC-016: sorts by a mapped field id via the allow-listed JSON path', function () {
    $actor = leadsImportActorWith(['import']);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        'sortModel' => [['colId' => 'email', 'sort' => 'asc']],
    ])->assertOk();

    expect($response->json('items.0.values.email'))->toBe('a@example.com')
        ->and($response->json('items.2.values.email'))->toBe('c@example.com');
});

it('AC-016: filters by `status`', function () {
    $actor = leadsImportActorWith(['import']);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        'filterModel' => ['status' => ['filter' => 'error']],
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.status'))->toBe('error');
});

it('AC-016: filters by a mapped field id via LIKE on the JSON-extracted value', function () {
    $actor = leadsImportActorWith(['import']);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        'filterModel' => ['first_name' => ['filter' => 'anna']],
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.values.first_name'))->toBe('Anna');
});

it('AC-016: a disallowed sort colId is silently ignored, never reaching raw SQL', function () {
    $actor = leadsImportActorWith(['import']);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        // `mapped_values` is a real column, but NOT in the allow-list (only
        // `row_number`/`status`/mapped field ids are) — an attempt to sort by
        // it directly (or by SQL injection payloads) must be ignored, not
        // executed, and must not error the request.
        'sortModel' => [['colId' => 'mapped_values', 'sort' => 'asc']],
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(3);
});

it('AC-016: a disallowed filter colId (including an injection payload) is silently ignored', function () {
    $actor = leadsImportActorWith(['import']);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        'filterModel' => [
            '1=1; DROP TABLE import_run_rows; --' => ['filter' => 'x'],
            'raw_values' => ['filter' => 'x'],
        ],
    ])->assertOk();

    // Both keys ignored (outside the allow-list): all 3 rows still returned,
    // and the table survives (the very next assertion still queries it).
    expect($response->json('pagination.total'))->toBe(3);

    $this->assertDatabaseCount('import_run_rows', 3);
});

it('AC-016: the global `search` term matches inside mapped_values', function () {
    $actor = leadsImportActorWith(['import']);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/imports/leads/{$run->id}/rows", [
        'search' => 'bruno',
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.values.first_name'))->toBe('Bruno');
});

it('403 without leads.import', function () {
    $actor = leadsImportActorWith([]);
    $run = reviewingLeadsRun($actor);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertForbidden();
});

it('pages the rows of a run started by another user (runs are shared)', function () {
    $actor = leadsImportActorWith(['import']);
    $otherUser = User::factory()->create();
    $run = reviewingLeadsRun($otherUser);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertOk();
});
