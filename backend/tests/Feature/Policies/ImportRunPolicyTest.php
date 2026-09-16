<?php

use App\Models\ImportRun;
use App\Models\User;
use App\Policies\ImportRunPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// The import module has no permission set of its own (the former `import-runs.*`
// was removed 2026-07-17): every ability is gated by the lead module's single
// `leads.import`; runs are shared, so no ability checks ownership.
// ---------------------------------------------------------------------------

it('denies every ability without leads.import, even for an owned run', function () {
    $user = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $user->id, 'resource' => 'leads']);

    expect($user->can('view', $run))->toBeFalse()
        ->and($user->can('delete', $run))->toBeFalse()
        ->and($user->can('viewAny', ImportRun::class))->toBeFalse()
        ->and($user->can('create', ImportRun::class))->toBeFalse();
});

it('grants view/delete once the actor has leads.import', function () {
    Permission::findOrCreate('leads.import');

    $user = User::factory()->create();
    $user->givePermissionTo('leads.import');
    $run = ImportRun::factory()->create(['user_id' => $user->id, 'resource' => 'leads']);

    expect($user->can('view', $run))->toBeTrue()
        ->and($user->can('delete', $run))->toBeTrue()
        ->and($user->can('viewAny', ImportRun::class))->toBeTrue()
        ->and($user->can('create', ImportRun::class))->toBeTrue();
});

it('grants view/update/delete on a run started by someone else (runs are shared)', function () {
    Permission::findOrCreate('leads.import');

    $user = User::factory()->create();
    $user->givePermissionTo('leads.import');
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads']);

    expect($user->can('view', $run))->toBeTrue()
        ->and($user->can('update', $run))->toBeTrue()
        ->and($user->can('delete', $run))->toBeTrue();
});

it('contributes no permissions of its own to the catalog (reuses leads.import)', function () {
    expect((new ImportRunPolicy)->permissions())->toBe([]);
});
