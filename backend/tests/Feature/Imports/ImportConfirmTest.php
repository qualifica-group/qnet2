<?php

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubImportDefinition;

uses(RefreshDatabase::class);

if (! function_exists('stubImportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<int, string>  $importRunAbilities  the `import-runs.*` MODULE
     *                                                  abilities (spec 0034), independent of the
     *                                                  domain `business-functions.*` ones above
     */
    function stubImportActorWith(array $abilities, array $importRunAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        grantImportRunsPermissions($user, $importRunAbilities);

        return $user;
    }
}

if (! function_exists('registerStubImportDomain')) {
    function registerStubImportDomain(): void
    {
        config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);
    }
}

// ---------------------------------------------------------------------------
// AC-009 — POST /api/imports/{domain}/{importRun}/confirm
// ---------------------------------------------------------------------------

it('200: awaiting_confirmation -> processing + dispatches ProcessImportJob', function () {
    registerStubImportDomain();
    Queue::fake();
    $actor = stubImportActorWith(['import'], ['update']);
    $run = ImportRun::factory()->awaitingConfirmation()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/stub-widgets/{$run->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'processing')
        ->assertJsonPath('data.import_run.imported_rows', null);

    expect($run->fresh()->status)->toBe(ImportStatus::Processing);
    Queue::assertPushed(ProcessImportJob::class);
});

it('422 when the run is not in awaiting_confirmation', function () {
    registerStubImportDomain();
    Queue::fake();
    $actor = stubImportActorWith(['import'], ['update']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets', 'status' => ImportStatus::Validating]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/stub-widgets/{$run->id}/confirm")->assertStatus(422);

    Queue::assertNotPushed(ProcessImportJob::class);
});

it('403 without {resource}.import', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith([]);
    $run = ImportRun::factory()->awaitingConfirmation()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/stub-widgets/{$run->id}/confirm")->assertForbidden();
});

it('confirms a run started by another user (runs are shared)', function () {
    registerStubImportDomain();
    Queue::fake();
    $actor = stubImportActorWith(['import'], ['update']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->awaitingConfirmation()->create(['user_id' => $otherUser->id, 'resource' => 'stub-widgets']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/stub-widgets/{$run->id}/confirm")->assertOk();
});

it('404 for a run whose resource does not match the route domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    $run = ImportRun::factory()->awaitingConfirmation()->create(['user_id' => $actor->id, 'resource' => 'some-other-domain']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/stub-widgets/{$run->id}/confirm")->assertNotFound();
});
