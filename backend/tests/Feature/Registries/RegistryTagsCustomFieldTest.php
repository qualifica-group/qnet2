<?php

use App\Models\Registry;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// The registry tags live as a custom field provisioned by the client template
// (`registries.tags`, relation many -> tags): these cover the write path
// end-to-end against the seeded definition, not a hand-built one.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The template's layout step uploads the letterhead binary.
    Storage::fake(config('attachments.disk'));
    test()->seed(QualificaTemplateSeeder::class);

    Permission::findOrCreate('registries.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('registries.update');
    Sanctum::actingAs($actor);
});

it('stores the selected tag ids on the registry', function (): void {
    $registry = Registry::factory()->create();
    $tags = Tag::factory()->count(2)->create();

    $this->patchJson("/api/registries/{$registry->id}", [
        'custom_fields' => ['tags' => $tags->pluck('id')->all()],
    ])->assertOk();

    expect($registry->fresh()->custom_fields)->toBe(['tags' => $tags->pluck('id')->all()]);
});

it('rejects a tag id that does not exist', function (): void {
    $registry = Registry::factory()->create();

    $this->patchJson("/api/registries/{$registry->id}", [
        'custom_fields' => ['tags' => [999999]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['custom_fields.tags']);
});
