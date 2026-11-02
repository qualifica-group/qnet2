<?php

use App\Models\MigrationPlan;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-002 — default plan is MigrationOrder flattened, all enabled
// ---------------------------------------------------------------------------

it('current(): defaults to MigrationOrder order with every source enabled when no plan is saved', function () {
    // REQUIREMENT CHANGED (spec 0295 D-14): `attachments` is available only with the legacy files base URL.
    config(['migrations.legacy_files_base_url' => 'https://legacy-files.test']);
    $plan = app(MigrationPlanService::class)->current();

    $keys = array_column($plan, 'source');
    $registered = array_keys(config('migrations.definitions'));

    expect($keys[0])->toBe('business-functions')
        ->and(collect($plan)->every(fn (array $item): bool => $item['enabled'] === true))->toBeTrue()
        ->and(count($plan))->toBe(count($registered));
});

// ---------------------------------------------------------------------------
// AC-003 — reconcile: drop unknown key, keep order, append missing registered
// ---------------------------------------------------------------------------

it('current(): drops an unregistered key, preserves stored order and appends a missing registered source', function () {
    MigrationPlan::query()->create(['sources' => [
        ['source' => 'users', 'enabled' => false],
        ['source' => 'ghost-source', 'enabled' => true],
        ['source' => 'companies', 'enabled' => true],
    ]]);

    $plan = app(MigrationPlanService::class)->current();
    $keys = array_column($plan, 'source');

    // Stored order preserved for known keys, ghost dropped.
    expect(array_slice($keys, 0, 2))->toBe(['users', 'companies'])
        ->and($keys)->not->toContain('ghost-source')
        // 'business-functions' was missing from the stored plan -> appended, enabled.
        ->and($keys)->toContain('business-functions')
        ->and(collect($plan)->firstWhere('source', 'users')['enabled'])->toBeFalse()
        ->and(collect($plan)->firstWhere('source', 'business-functions')['enabled'])->toBeTrue()
        ->and(count($plan))->toBe(count(config('migrations.definitions')));
});

// ---------------------------------------------------------------------------
// AC-004 — save is a singleton upsert (last save wins)
// ---------------------------------------------------------------------------

it('save(): keeps a single row and the last save wins', function () {
    $service = app(MigrationPlanService::class);

    $service->save([['source' => 'roles', 'enabled' => true]]);
    $service->save([['source' => 'companies', 'enabled' => false]]);

    expect(MigrationPlan::query()->count())->toBe(1)
        ->and(collect($service->current())->firstWhere('source', 'companies')['enabled'])->toBeFalse();
});

it('enabledSources(): returns only enabled source keys in order', function () {
    app(MigrationPlanService::class)->save([
        ['source' => 'companies', 'enabled' => true],
        ['source' => 'users', 'enabled' => false],
        ['source' => 'roles', 'enabled' => true],
    ]);

    // reconcile appends the remaining registered sources as enabled; assert the
    // explicitly disabled one is absent and the enabled ones keep their order.
    $enabled = app(MigrationPlanService::class)->enabledSources();

    expect($enabled)->not->toContain('users')
        ->and(array_slice($enabled, 0, 2))->toBe(['companies', 'roles']);
});

// ---------------------------------------------------------------------------
// AC-009 — GET /api/migrations/plan
// ---------------------------------------------------------------------------

it('show: 200 with the reconciled plan (source, label, enabled) for a super-admin', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->getJson('/api/migrations/plan')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.plan.sources.0.source', 'business-functions')
        ->assertJsonPath('data.plan.sources.0.enabled', true)
        ->assertJsonStructure(['data' => ['plan' => ['sources' => [['source', 'label', 'enabled']]]]]);
});

it('show: 403 for a non-super-admin', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/migrations/plan')->assertForbidden();
});

it('show: 401 for an anonymous request', function () {
    $this->getJson('/api/migrations/plan')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// AC-010 — PUT /api/migrations/plan
// ---------------------------------------------------------------------------

it('update: 200 persists the reordered subset and returns it reconciled', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->putJson('/api/migrations/plan', ['sources' => [
        ['source' => 'companies', 'enabled' => true],
        ['source' => 'users', 'enabled' => false],
    ]])
        ->assertOk()
        ->assertJsonPath('data.plan.sources.0.source', 'companies')
        ->assertJsonPath('data.plan.sources.1.source', 'users')
        ->assertJsonPath('data.plan.sources.1.enabled', false);

    expect(MigrationPlan::query()->count())->toBe(1);
});

it('update: 422 for an unknown source', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->putJson('/api/migrations/plan', ['sources' => [
        ['source' => 'ghost-source', 'enabled' => true],
    ]])->assertStatus(422);
});

it('update: 422 for a duplicated source', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->putJson('/api/migrations/plan', ['sources' => [
        ['source' => 'users', 'enabled' => true],
        ['source' => 'users', 'enabled' => false],
    ]])->assertStatus(422);
});

it('update: 422 for an empty list', function () {
    Sanctum::actingAs(migrationsSuperAdminActor());

    $this->putJson('/api/migrations/plan', ['sources' => []])->assertStatus(422);
});

it('update: 403 for a non-super-admin', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/migrations/plan', ['sources' => [
        ['source' => 'users', 'enabled' => true],
    ]])->assertForbidden();
});
