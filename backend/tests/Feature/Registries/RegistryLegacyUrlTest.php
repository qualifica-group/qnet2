<?php

use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0378: the detail of a migrated anagrafica links its legacy page, shown
 * under the contacts as "can't find a contact? check the legacy QNet".
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['migrations.legacy_files_base_url' => 'https://legacy.test']);
    Permission::findOrCreate('registries.view');
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('registries.view');
    Sanctum::actingAs($viewer);
});

it('links a migrated anagrafica to its legacy page', function () {
    $registry = Registry::factory()->create(['old_id' => 702]);

    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('data.legacy_url', 'https://legacy.test/companyshow/702');
});

it('gives no link to an anagrafica created in QNet or without the legacy host', function () {
    $local = Registry::factory()->create(['old_id' => null]);
    $migrated = Registry::factory()->create(['old_id' => 703]);

    $this->getJson("/api/registries/{$local->id}")->assertOk()->assertJsonPath('data.legacy_url', null);

    config(['migrations.legacy_files_base_url' => '']);

    $this->getJson("/api/registries/{$migrated->id}")->assertOk()->assertJsonPath('data.legacy_url', null);
});
