<?php

use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('AC-019: permissions:sync creates ai-assistant.use', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'ai-assistant.use')->exists())->toBeTrue();
});

it('exposes the AI assistant navigation item gated by the permission', function () {
    $item = collect(config('navigation.items'))->firstWhere('key', 'ai-assistant');

    expect($item)->not->toBeNull()
        ->and($item['route'])->toBe('/ai-assistant')
        ->and($item['label'])->toBe('navigation.aiAssistant')
        ->and($item['permission'])->toBe('ai-assistant.use')
        ->and(app(NavigationService::class)->permissions())->toContain('ai-assistant.use');
});
