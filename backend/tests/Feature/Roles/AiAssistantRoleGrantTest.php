<?php

use App\Models\Role;
use App\Policies\AiConversationPolicy;
use Database\Seeders\DemoRolesSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives ai-assistant.use to every role the demo seeder creates (everyone has the assistant, spec 0433)', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);

    foreach (['admin', 'manager', 'operator', 'user', 'viewer'] as $name) {
        expect(Role::findByName($name)->hasPermissionTo(AiConversationPolicy::PERMISSION))->toBeTrue("role {$name}");
    }
});

it('gives ai-assistant.use to every role the qualifica seeder creates', function () {
    $this->seed(QualificaRoleSeeder::class);

    $without = Role::query()->where('name', '!=', 'super-admin')->get()
        ->reject(fn (Role $role): bool => $role->hasPermissionTo(AiConversationPolicy::PERMISSION))
        ->pluck('name')->all();

    expect(Role::query()->count())->toBeGreaterThan(1)->and($without)->toBe([]);
});
