<?php

use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Services\MigrationPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Spec 0285, D-5: in users link-only mode the business-function members pass
// (manager + operators from the legacy) is not available.

// ---------------------------------------------------------------------------
// AC-008 — availability on the source list and the plan
// ---------------------------------------------------------------------------

it('index: business-function-members is unavailable in users link-only mode', function () {
    config(['migrations.users_link_only' => true]);
    Sanctum::actingAs(migrationsSuperAdminActor());

    $sources = collect($this->getJson('/api/migrations')->assertOk()->json('data.sources'))->keyBy('key');

    expect($sources['business-function-members']['available'])->toBeFalse()
        ->and($sources['users']['available'])->toBeTrue()
        ->and($sources['business-functions']['available'])->toBeTrue();
});

it('index: every source is available with the default full users import', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $sources = collect($this->getJson('/api/migrations')->assertOk()->json('data.sources'));

    expect($sources->every(fn (array $source): bool => $source['available'] === true))->toBeTrue();
});

it('show plan: business-function-members is unavailable and disabled in users link-only mode, even when saved as enabled', function () {
    config(['migrations.users_link_only' => true]);
    app(MigrationPlanService::class)->save([['source' => 'business-function-members', 'enabled' => true]]);
    Sanctum::actingAs(migrationsSuperAdminActor());

    $plan = collect($this->getJson('/api/migrations/plan')->assertOk()->json('data.plan.sources'))->keyBy('source');

    expect($plan['business-function-members']['available'])->toBeFalse()
        ->and($plan['business-function-members']['enabled'])->toBeFalse()
        ->and($plan['users']['available'])->toBeTrue()
        ->and($plan['users']['enabled'])->toBeTrue();
});

it('plan: the saved flag of an unavailable source comes back once the mode is switched off', function () {
    config(['migrations.users_link_only' => true]);
    $service = app(MigrationPlanService::class);
    $service->save([['source' => 'business-function-members', 'enabled' => true]]);

    config(['migrations.users_link_only' => false]);

    expect(collect($service->current())->firstWhere('source', 'business-function-members')['enabled'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-009 — refused by the single import and left out of the mass import
// ---------------------------------------------------------------------------

it('import: 422 for business-function-members in users link-only mode, no run created', function () {
    config(['migrations.users_link_only' => true]);
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->postJson('/api/migrations/business-function-members/import')
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(MigrationRun::query()->count())->toBe(0);
    Queue::assertNotPushed(RunMigrationJob::class);
});

it('import: 201 for business-function-members with the default full users import', function () {
    Queue::fake();
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->postJson('/api/migrations/business-function-members/import')->assertStatus(201);

    Queue::assertPushed(RunMigrationJob::class);
});

it('enabledSources(): the mass import leaves out business-function-members in users link-only mode', function () {
    config(['migrations.users_link_only' => true]);
    $service = app(MigrationPlanService::class);
    $service->save([
        ['source' => 'users', 'enabled' => true],
        ['source' => 'business-function-members', 'enabled' => true],
    ]);

    expect($service->enabledSources())->toContain('users')
        ->not->toContain('business-function-members');
});
