<?php

use App\Integrations\IntegrationRegistry;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Formalab\FormalabServiceProvider;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-001
it('exposes nothing about Formalab when the integration is disabled', function () {
    $admin = formalabSuperAdmin();
    Sanctum::actingAs($admin);

    $this->getJson('/api/integrations')->assertOk()->assertJsonPath('data.active', []);

    foreach (['get' => ['status', 'classes', 'work-orders/1'], 'post' => ['sync', 'test-connection', 'link-work-orders', 'webhooks/classes']] as $method => $paths) {
        foreach ($paths as $path) {
            $this->{$method.'Json'}("/api/integrations/formalab/{$path}")->assertNotFound();
        }
    }

    $this->getJson('/api/tables/formalab-call-logs/columns')->assertNotFound();
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data'))->all())->not->toContain('formalab');
    $keys = collect($this->getJson('/api/system-health')->assertOk()->json('data.checks'))->pluck('key')->all();
    expect($keys)->not->toContain('formalab')
        ->and(collect(app(Schedule::class)->events())->map->description->filter()->implode(' '))->not->toContain('formalab');
});

// AC-002
it('shows the integration to a super-admin only when it is enabled', function () {
    formalabEnable();
    Sanctum::actingAs(formalabSuperAdmin());

    $this->getJson('/api/integrations')->assertOk()->assertJsonPath('data.active', ['formalab']);

    $navigation = $this->getJson('/api/navigation')->json('data');
    $develop = collect($navigation)->firstWhere('key', 'develop');
    $group = collect($develop['children'])->firstWhere('key', 'external-services');
    expect($group['label'])->toBe('integrations:nav.externalServices')
        ->and(collect($group['children'])->pluck('route', 'key')->all())->toBe(['formalab' => '/dev/formalab'])
        ->and(collect($develop['children'])->pluck('key'))->not->toContain('formalab');

    $keys = collect($this->getJson('/api/system-health')->assertOk()->json('data.checks'))->pluck('key')->all();
    expect($keys)->toBe(['database', 'email', 'queue', 'security', 'formalab']);

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNode($this->getJson('/api/navigation')->json('data') ?? [], 'formalab'))->toBeNull();
});

it('schedules the sync and the prune only when enabled', function () {
    formalabEnable(['sync_interval_minutes' => 5]);

    $events = collect(app(Schedule::class)->events())->keyBy('description');

    expect($events->get('formalab:sync-classes')->expression)->toBe('*/5 * * * *')
        ->and($events->has('formalab:prune'))->toBeTrue();
});

it('keeps the registry empty by default and de-duplicates activations', function () {
    $registry = new IntegrationRegistry;
    $registry->activate('a');
    $registry->activate('a');

    expect(app(IntegrationRegistry::class)->active())->toBe([])->and($registry->active())->toBe(['a']);
});

it('adds a single menu entry even when the provider boots twice (cached config)', function () {
    formalabEnable();
    $this->app->register(FormalabServiceProvider::class, force: true);
    Sanctum::actingAs(formalabSuperAdmin());

    $develop = collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'develop');
    $groups = collect($develop['children'])->where('key', 'external-services');

    expect($groups)->toHaveCount(1)
        ->and(collect($groups->first()['children'])->where('key', 'formalab'))->toHaveCount(1);
});
