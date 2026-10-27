<?php

use App\Models\Workspace;
use App\Services\Workspaces\NavigationCatalogue;
use Database\Seeders\QualificaCatalog\WorkspaceCatalogue;
use Database\Seeders\QualificaWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the three Qualifica apps with their menu entries', function () {
    test()->seed(QualificaWorkspaceSeeder::class);

    $apps = Workspace::query()->with('items')->orderBy('sort_order')->get();

    expect($apps->pluck('name')->all())->toBe(['Qualifica Contabilità', 'Qualifica HR', 'Qualifica Sales'])
        ->and($apps->pluck('slug')->all())->toBe(['contabilita', 'hr', 'sales']);

    foreach ($apps as $app) {
        expect($app->navigationKeys())->toContain('dashboard', 'tasks');
    }

    // REQUIREMENT CHANGED (user directive 2026-10-27): each app holds only its own job's pages.
    [$accounting, $hr, $sales] = $apps->map->navigationKeys()->all();

    expect($accounting)->toContain('invoices', 'ledger', 'purchase-orders')
        ->and(array_intersect($accounting, ['absences', 'time-entries', 'leads', 'users']))->toBe([])
        ->and($hr)->toContain('users', 'org-chart', 'absences', 'time-entries')
        ->and(array_intersect($hr, ['invoices', 'leads']))->toBe([])
        ->and($sales)->toContain('leads', 'opportunities', 'quotes')
        ->and(array_intersect($sales, ['absences', 'time-entries', 'ledger', 'users']))->toBe([]);
});

it('lists only entries that exist in the menu config', function () {
    $known = app(NavigationCatalogue::class)->keys();

    foreach (WorkspaceCatalogue::apps() as $app) {
        expect(array_diff($app['navigation_keys'], $known))->toBe([]);
    }
});

it('is create-only: a re-run duplicates nothing and keeps the super-admin edits', function () {
    test()->seed(QualificaWorkspaceSeeder::class);
    $hr = Workspace::query()->where('slug', 'hr')->sole();
    $hr->update(['name' => 'Personale']);
    $hr->items()->where('navigation_key', 'stock')->delete();

    test()->seed(QualificaWorkspaceSeeder::class);

    expect(Workspace::query()->count())->toBe(3)
        ->and($hr->fresh()->name)->toBe('Personale')
        ->and($hr->fresh()->load('items')->navigationKeys())->not->toContain('stock');
});
