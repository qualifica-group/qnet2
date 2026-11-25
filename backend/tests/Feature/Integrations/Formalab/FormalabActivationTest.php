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

    foreach (['get' => ['status', 'classes', 'work-orders/1'], 'post' => ['sync', 'test-connection', 'webhooks/classes']] as $method => $paths) {
        foreach ($paths as $path) {
            $this->{$method.'Json'}("/api/integrations/formalab/{$path}")->assertNotFound();
        }
    }

    $this->getJson('/api/tables/formalab-call-logs/columns')->assertNotFound();
    $this->getJson('/api/tables/formalab-classes/columns')->assertNotFound();
    $this->getJson('/api/integrations/formalab/classes/for-select')->assertNotFound();
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

    // REQUIREMENT CHANGED (spec 0426 D-8): own section "Integrazioni esterne" before Develop, Formalab group with status and classes
    $navigation = collect($this->getJson('/api/navigation')->json('data'));
    $keys = $navigation->pluck('key')->all();
    $section = $navigation->firstWhere('key', 'external-integrations');
    $group = collect($section['children'])->firstWhere('key', 'formalab-integration');
    expect($section['label'])->toBe('integrations:nav.externalIntegrations')
        ->and(array_search('external-integrations', $keys, true))->toBe(array_search('develop', $keys, true) - 1)
        ->and($group['label'])->toBe('formalab:nav.label')
        ->and(collect($group['children'])->pluck('route', 'key')->all())->toBe(['formalab' => '/dev/formalab', 'formalab-classes' => '/dev/formalab/classes'])
        ->and(navigationNodeKeys($navigation->firstWhere('key', 'develop')['children'])->all())->not->toContain('formalab');

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

// REQUIREMENT CHANGED (user request 2026-11-23): the default is every hour, at minute 0
it('syncs every hour by default, and hourly for any interval of an hour or more', function (?int $minutes) {
    formalabEnable($minutes === null ? [] : ['sync_interval_minutes' => $minutes]);

    expect(collect(app(Schedule::class)->events())->keyBy('description')->get('formalab:sync-classes')->expression)->toBe('0 * * * *');
})->with(['default' => [null], 'one hour' => [60], 'two hours' => [120]]);

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

    $sections = collect($this->getJson('/api/navigation')->json('data'))->where('key', 'external-integrations');
    $groups = collect($sections->first()['children'])->where('key', 'formalab-integration');

    expect($sections)->toHaveCount(1)
        ->and($groups)->toHaveCount(1)
        ->and(collect($groups->first()['children'])->where('key', 'formalab'))->toHaveCount(1);
});
