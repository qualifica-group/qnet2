<?php

use App\Jobs\GenerateExportJob;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management/transfer (spec 0079, migrated onto the Quote
// by spec 0086 D-6): the read/grid surface and the two invariants that
// follow from it — AC-018 -> AC-021, AC-024, AC-026.

uses(RefreshDatabase::class);

if (! function_exists('transferGridActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function transferGridActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'transferContact', 'export'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('transferGridSite')) {
    function transferGridSite(): OperationalSite
    {
        return OperationalSite::factory()->withAddress()->create();
    }
}

// ---------------------------------------------------------------------------
// AC-018 — RequestManagementResource
// ---------------------------------------------------------------------------

it('the work panel exposes is_transferred and transferred_from {id,label}, null when never transferred (AC-018)', function () {
    $viewer = transferGridActorWith(['viewAll', 'view']);
    $untouched = Quote::factory()->create();
    Sanctum::actingAs($viewer);

    $this->getJson("/api/request-management/{$untouched->id}")
        ->assertOk()
        ->assertJsonPath('data.is_transferred', false)
        ->assertJsonPath('data.transferred_from', null);

    $originSite = transferGridSite();
    $destinationSite = transferGridSite();
    $newOperator = User::factory()->create();
    $transferredRequest = Quote::factory()->create(['operational_site_id' => $originSite->id]);
    $transferActor = transferGridActorWith(['update', 'viewAll', 'transferContact']);
    Sanctum::actingAs($transferActor);
    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$transferredRequest->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    $originLabel = OperationalSiteLabel::summarize($originSite->fresh(['addresses.city']));

    Sanctum::actingAs($viewer);
    $this->getJson("/api/request-management/{$transferredRequest->id}")
        ->assertOk()
        ->assertJsonPath('data.is_transferred', true)
        ->assertJsonPath('data.transferred_from.id', $originSite->id)
        ->assertJsonPath('data.transferred_from.label', $originLabel['label']);
});

// ---------------------------------------------------------------------------
// AC-019 / AC-020 / AC-021 — grid column: sort, filter, export
// ---------------------------------------------------------------------------

it('the grid sorts, boolean-filters and exports the is_transferred column (AC-019/AC-020/AC-021)', function () {
    Queue::fake();

    $actor = transferGridActorWith(['viewAny', 'viewAll', 'view']);
    // ExportController checks the ability against modelClass() (Quote, spec
    // 0086) via QuotePolicy, not this domain's own permission set —
    // pre-existing behaviour of the generic export framework, unrelated to
    // spec 0079.
    Permission::findOrCreate('quotes.export');
    $actor->givePermissionTo('quotes.export');
    // `is_transferred` is NOT fillable (AC-024): set directly, not via
    // the factory's mass-assigned `create()`.
    $transferred = Quote::factory()->create();
    $transferred->is_transferred = true;
    $transferred->save();
    Quote::factory()->create();
    Sanctum::actingAs($actor);

    // AC-019: sortable.
    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'is_transferred', 'sort' => 'desc']],
    ])->assertOk();

    // AC-020: boolean filter through the generic engine, bound parameters.
    $filtered = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['is_transferred' => ['values' => [true]]],
    ])->assertOk()->json('items');

    expect(collect($filtered))->each(fn ($item) => $item->toHaveKey('is_transferred', true));

    // AC-021: exportable like any other column.
    $this->postJson('/api/exports/request-management', [
        'format' => 'csv',
        'columns' => [['colId' => 'is_transferred', 'header' => 'Trasferito']],
        'sortModel' => [['colId' => 'is_transferred', 'sort' => 'asc']],
        'filterModel' => ['is_transferred' => ['values' => [true]]],
    ])->assertCreated();

    Queue::assertPushed(GenerateExportJob::class);
});

// ---------------------------------------------------------------------------
// AC-024 — no write path for the two tracking columns
// ---------------------------------------------------------------------------

it('PATCH never writes is_transferred or transferred_from_operational_site_id, even when submitted (AC-024)', function () {
    $actor = transferGridActorWith(['update', 'viewAll', 'view']);
    $quote = Quote::factory()->create();
    $otherSite = transferGridSite();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'is_transferred' => true,
        'transferred_from_operational_site_id' => $otherSite->id,
    ])->assertOk();

    $quote->refresh();
    expect($quote->is_transferred)->toBeFalse()
        ->and($quote->transferred_from_operational_site_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-026 — origin Sede deletion
// ---------------------------------------------------------------------------

it('deleting the origin Sede: no 409, the request survives, is_transferred stays true, the origin clears (AC-026)', function () {
    $actor = transferGridActorWith(['update', 'viewAll', 'transferContact']);
    $originSite = transferGridSite();
    $destinationSite = transferGridSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create(['operational_site_id' => $originSite->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    $originSite->delete();

    $quote->refresh();
    expect(Quote::query()->whereKey($quote->id)->exists())->toBeTrue()
        ->and($quote->is_transferred)->toBeTrue()
        ->and($quote->transferred_from_operational_site_id)->toBeNull();
});
