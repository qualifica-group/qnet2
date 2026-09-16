<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
// AC-008 — GET /api/imports/{domain}/{importRun}
// ---------------------------------------------------------------------------

it('200 with status and preview=null before awaiting_confirmation', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['view']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets', 'status' => ImportStatus::Validating]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.import_run.id', $run->id)
        ->assertJsonPath('data.import_run.status', 'validating')
        ->assertJsonPath('data.preview', null);
});

it('200 with the preview block once awaiting_confirmation', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['view']);
    $run = ImportRun::factory()->awaitingConfirmation()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.status', 'awaiting_confirmation')
        ->assertJsonPath('data.preview.columns', ['name', 'type'])
        ->assertJsonPath('data.preview.valid_sample.0.name', 'Sales')
        ->assertJsonPath('data.preview.invalid_sample.0.row_number', 2);
});

it('403 without import-runs.view (spec 0034: reads no longer require {resource}.import)', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")->assertForbidden();
});

it('shows a run started by another user (runs are shared)', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['view']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'stub-widgets']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.id', $run->id);
});

it('404 for a run whose resource does not match the route domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['view']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'some-other-domain']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")->assertNotFound();
});

it('404 for a non-existent import run', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/imports/stub-widgets/999999')->assertNotFound();
});

it('404 for an unregistered domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/unknown-domain/{$run->id}")->assertNotFound();
});
