<?php

use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
// AC-010 — GET /api/imports/{domain}/{importRun}/errors
// ---------------------------------------------------------------------------

it('downloads the full errors CSV (header = template columns + row_number + errors)', function () {
    registerStubImportDomain();
    Storage::fake('local');
    $reportPath = 'imports/report-errors.csv';
    Storage::disk('local')->put($reportPath, "name,type,row_number,errors\n,,2,name is required.\n");

    $actor = stubImportActorWith(['import'], ['view']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'error_report_path' => $reportPath,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->get("/api/imports/stub-widgets/{$run->id}/errors")->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain("stub-widgets-import-errors-{$run->id}.csv")
        ->and($response->streamedContent())->toContain('name,type,row_number,errors');
});

it('404 when the run has no error report', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['view']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets', 'error_report_path' => null]);
    Sanctum::actingAs($actor);

    $this->get("/api/imports/stub-widgets/{$run->id}/errors")->assertNotFound();
});

it('403 without import-runs.view (spec 0034: reads no longer require {resource}.import)', function () {
    registerStubImportDomain();
    Storage::fake('local');
    $actor = stubImportActorWith([]);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-widgets', 'error_report_path' => 'imports/x.csv']);
    Sanctum::actingAs($actor);

    $this->get("/api/imports/stub-widgets/{$run->id}/errors")->assertForbidden();
});

it('downloads the errors report of a run started by another user (runs are shared)', function () {
    registerStubImportDomain();
    Storage::fake('local');
    Storage::disk('local')->put('imports/x.csv', "row,error\n");
    $actor = stubImportActorWith(['import'], ['view']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'stub-widgets', 'error_report_path' => 'imports/x.csv']);
    Sanctum::actingAs($actor);

    $this->get("/api/imports/stub-widgets/{$run->id}/errors")->assertOk();
});

it('404 for a run whose resource does not match the route domain', function () {
    registerStubImportDomain();
    Storage::fake('local');
    $actor = stubImportActorWith(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'some-other-domain', 'error_report_path' => 'imports/x.csv']);
    Sanctum::actingAs($actor);

    $this->get("/api/imports/stub-widgets/{$run->id}/errors")->assertNotFound();
});
