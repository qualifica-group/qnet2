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

// ---------------------------------------------------------------------------
// create — D-7a/b/c (AC-011..014)
// ---------------------------------------------------------------------------

it('create: the FIRST layout of a module becomes default even when not requested (AC-011)', function () {
    $actor = documentLayoutUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'First', 'code' => 'first', 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);
});

it('create: the SECOND layout of a module is NOT default unless requested, first stays default (AC-012)', function () {
    $actor = documentLayoutUserWith(['create']);
    $first = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'Second', 'code' => 'second', 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_default', false);

    expect($first->fresh()->is_default)->toBeTrue();
});

it('create: is_default:true reassigns the default in the SAME transaction (AC-013)', function () {
    $actor = documentLayoutUserWith(['create']);
    $previousDefault = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'New Default', 'code' => 'new_default', 'module' => 'quotes', 'is_default' => true,
        'config' => DocumentLayoutFactory::minimalConfig(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    expect($previousDefault->fresh()->is_default)->toBeFalse()
        ->and(DocumentLayout::where('module', 'quotes')->where('is_default', true)->count())->toBe(1);
});

it('create: is_default:true with is_active:false is 422, no record created (AC-014)', function () {
    $actor = documentLayoutUserWith(['create']);
    Sanctum::actingAs($actor);

    $countBefore = DocumentLayout::count();

    $this->postJson('/api/document-layouts', [
        'name' => 'Invalid Default', 'code' => 'invalid_default', 'module' => 'quotes',
        'is_default' => true, 'is_active' => false, 'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertStatus(422)->assertJsonValidationErrors('is_default');

    expect(DocumentLayout::count())->toBe($countBefore);
});

// ---------------------------------------------------------------------------
// update — D-7c/d/e (AC-022..024)
// ---------------------------------------------------------------------------

it('update: {is_active:false} deactivates a NON-default layout (AC-022)', function () {
    $actor = documentLayoutUserWith(['update']);
    DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false, 'is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('update: {is_active:false} on the DEFAULT layout is 422 default_cannot_be_deactivated (AC-022)', function () {
    $actor = documentLayoutUserWith(['update']);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true, 'is_active' => true]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/document-layouts/{$target->id}", ['is_active' => false])
        ->assertStatus(422)->assertJsonValidationErrors('is_active');

    expect($response->json('errors.is_active.0'))->toBe(__('document_layouts.default_cannot_be_deactivated'))
        ->and($target->fresh()->is_active)->toBeTrue();
});

it('update: {is_default:false} on the default layout is 422 default_must_be_reassigned, flag stays true (AC-023)', function () {
    $actor = documentLayoutUserWith(['update']);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/document-layouts/{$target->id}", ['is_default' => false])
        ->assertStatus(422)->assertJsonValidationErrors('is_default');

    expect($response->json('errors.is_default.0'))->toBe(__('document_layouts.default_must_be_reassigned'))
        ->and($target->fresh()->is_default)->toBeTrue();
});

it('update: {is_default:true} on an active non-default layout promotes it, exactly one default at DB (AC-024)', function () {
    $actor = documentLayoutUserWith(['update']);
    $previousDefault = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false, 'is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['is_default' => true])
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect($previousDefault->fresh()->is_default)->toBeFalse()
        ->and(DocumentLayout::where('module', 'quotes')->where('is_default', true)->count())->toBe(1);
});

it('update: {is_default:true, is_active:false} together is 422 (D-7c)', function () {
    $actor = documentLayoutUserWith(['update']);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false, 'is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['is_default' => true, 'is_active' => false])
        ->assertStatus(422)->assertJsonValidationErrors('is_default');
});

// ---------------------------------------------------------------------------
// delete — D-7 guard (AC-026/027)
// ---------------------------------------------------------------------------

it('delete: the default layout is 422 default_cannot_be_deleted while siblings exist (AC-026)', function () {
    $actor = documentLayoutUserWith(['delete']);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false]);
    Sanctum::actingAs($actor);

    $response = $this->deleteJson("/api/document-layouts/{$target->id}")
        ->assertStatus(422)->assertJsonValidationErrors('is_default');

    expect($response->json('errors.is_default.0'))->toBe(__('document_layouts.default_cannot_be_deleted'));
    $this->assertDatabaseHas('document_layouts', ['id' => $target->id]);
});

it('delete: the default layout succeeds when it is the ONLY layout of its module (AC-027)', function () {
    $actor = documentLayoutUserWith(['delete']);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/document-layouts/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('document_layouts', ['id' => $target->id]);
});
