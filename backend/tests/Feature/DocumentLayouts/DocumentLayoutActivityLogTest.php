<?php

use App\Models\DocumentLayout;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('documentLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-layouts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-layouts.{$ability}");
        }

        return $user;
    }
}

/**
 * AC-100: this is the ONLY test that proves LogsModelActivity (wave 1's
 * DocumentLayout model), config/activity-log.php
 * ('document-layouts' => DocumentLayout::class) and the morph map
 * ('document_layout' => DocumentLayout::class in AppServiceProvider, wave 1)
 * are ALL THREE wired correctly (mirrors PaymentMethodActivityLogTest).
 */
it('create + update produce created/updated activity-log events with the changed fields (AC-100)', function () {
    $actor = documentLayoutUserWith(['create', 'update', 'view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/document-layouts', [
        'name' => 'Layout Standard', 'code' => 'layout_standard', 'module' => 'quotes',
        'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertCreated();
    $id = $created->json('data.id');

    $this->patchJson("/api/document-layouts/{$id}", ['name' => 'Layout Standard Plus', 'description' => 'Updated description'])
        ->assertOk();

    $response = $this->getJson("/api/activity-log/document-layouts/{$id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => ['items', 'next_cursor']]);

    $items = collect($response->json('data.items'));

    $createdEvent = $items->firstWhere('event', 'created');
    $updatedEvent = $items->firstWhere('event', 'updated');

    expect($createdEvent)->not->toBeNull()
        ->and($updatedEvent)->not->toBeNull();

    $updatedFields = collect($updatedEvent['changes'])->pluck('field')->all();
    expect($updatedFields)->toContain('name', 'description');

    $byField = collect($updatedEvent['changes'])->keyBy('field');
    expect($byField['name']['old_value'])->toBe('Layout Standard')
        ->and($byField['name']['new_value'])->toBe('Layout Standard Plus');
});

it('403 without document-layouts.viewActivity (AC-100)', function () {
    $actor = documentLayoutUserWith(['view']);
    $target = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/document-layouts/{$target->id}")->assertForbidden();
});

it('403 with document-layouts.viewActivity but without document-layouts.view (AC-100)', function () {
    $actor = documentLayoutUserWith(['viewActivity']);
    $target = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/document-layouts/{$target->id}")->assertForbidden();
});
