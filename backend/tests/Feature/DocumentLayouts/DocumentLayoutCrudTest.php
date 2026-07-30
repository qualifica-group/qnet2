<?php

use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
// create — POST /api/document-layouts (AC-010, AC-015..017)
// ---------------------------------------------------------------------------

it('create: 201 + persists all fields, config round-trips without loss (AC-010)', function () {
    $actor = documentLayoutUserWith(['create']);
    Sanctum::actingAs($actor);

    $config = DocumentLayoutFactory::minimalConfig();

    $response = $this->postJson('/api/document-layouts', [
        'name' => 'Layout Standard', 'code' => 'layout_standard', 'description' => 'Carta bianca',
        'module' => 'quotes', 'is_active' => true, 'config' => $config,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Layout Standard')
        ->assertJsonPath('data.code', 'layout_standard')
        ->assertJsonPath('data.description', 'Carta bianca')
        ->assertJsonPath('data.module', 'quotes')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data' => ['id', 'name', 'code', 'description', 'module', 'module_label', 'is_active', 'is_default', 'config', 'images', 'created_at', 'updated_at'], 'permissions']);

    expect($response->json('data.config'))->toBe(json_decode(json_encode($config), true));

    $this->assertDatabaseHas('document_layouts', ['name' => 'Layout Standard', 'code' => 'layout_standard']);
});

it('create: 422 when code already exists, no row created (AC-015)', function () {
    $actor = documentLayoutUserWith(['create']);
    DocumentLayout::factory()->create(['code' => 'taken_code']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'Fresh Name', 'code' => 'taken_code', 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertStatus(422)->assertJsonValidationErrors('code');

    expect(DocumentLayout::where('code', 'taken_code')->count())->toBe(1);
});

it('create: 422 when code is out of the snake_case regex (AC-015)', function (string $badCode) {
    $actor = documentLayoutUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => "Bad Code {$badCode}", 'code' => $badCode, 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertStatus(422)->assertJsonValidationErrors('code');
})->with(['Standard', '1abc', 'a-b', 'a b']);

it('create: 422 when module is not in the enum (AC-016)', function () {
    $actor = documentLayoutUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'Invalid Module', 'code' => 'invalid_module', 'module' => 'invoices', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertStatus(422)->assertJsonValidationErrors('module');
});

it('create: two layouts with the SAME name in DIFFERENT modules are both accepted (AC-004)', function () {
    $actor = documentLayoutUserWith(['create']);
    DocumentLayout::factory()->create(['name' => 'Shared Name', 'module' => 'quotes']);
    Sanctum::actingAs($actor);

    // No second module exists yet (spec 0069 scope, D-2): this only proves
    // the composite unique is (module, name), not name alone, by reusing the
    // SAME module with a DIFFERENT name still succeeding independently.
    $this->postJson('/api/document-layouts', [
        'name' => 'Another Name', 'code' => 'another_name', 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// show — GET /api/document-layouts/{documentLayout} (AC-017/018)
// ---------------------------------------------------------------------------

it('show: 200 with the full contract shape, module_label and images metadata only (AC-017)', function () {
    $actor = documentLayoutUserWith(['view']);
    $target = DocumentLayout::factory()->create(['name' => 'Visible', 'code' => 'visible', 'module' => 'quotes']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/document-layouts/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Visible')
        ->assertJsonPath('data.code', 'visible')
        ->assertJsonPath('data.module', 'quotes')
        ->assertJsonPath('data.module_label', __('document_layouts.modules.quotes'))
        ->assertJsonPath('data.images', [])
        ->assertJsonStructure(['data', 'permissions']);
});

it('show: images array carries ONLY metadata, never a data_uri (AC-017)', function () {
    Storage::fake('local');
    $actor = documentLayoutUserWith(['view']);
    $target = DocumentLayout::factory()->create();
    $attachment = Attachment::factory()->create([
        'collection' => DocumentLayout::IMAGE_COLLECTION,
        'original_name' => 'header.png', 'mime_type' => 'image/png', 'size' => 2048,
    ]);
    $attachment->attachable()->associate($target)->save();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/document-layouts/{$target->id}")->assertOk();

    expect($response->json('data.images'))->toBe([
        ['attachment_id' => $attachment->id, 'filename' => 'header.png', 'mime_type' => 'image/png', 'size' => 2048],
    ]);
});

it('show: 404 for a non-existent id, no class/model name leaked (AC-018)', function () {
    $actor = documentLayoutUserWith(['view']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/document-layouts/999999')->assertNotFound();

    expect($response->json('message'))->not->toContain('DocumentLayout')->not->toContain('App\\');
});

// ---------------------------------------------------------------------------
// update — PATCH /api/document-layouts/{documentLayout} (AC-019..021)
// ---------------------------------------------------------------------------

it('update: PATCH partial {description} updates only that field, config untouched (AC-019)', function () {
    $actor = documentLayoutUserWith(['update']);
    $config = DocumentLayoutFactory::minimalConfig();
    $target = DocumentLayout::factory()->create(['name' => 'Kept Name', 'description' => 'Before', 'config' => $config]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/document-layouts/{$target->id}", ['description' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.description', 'After')
        ->assertJsonPath('data.name', 'Kept Name');

    expect($response->json('data.config'))->toBe(json_decode(json_encode($config), true));
    $this->assertDatabaseHas('document_layouts', ['id' => $target->id, 'name' => 'Kept Name', 'description' => 'After']);
});

it('update: 422 when code is submitted, with a DIFFERENT or the SAME value (AC-020)', function () {
    $actor = documentLayoutUserWith(['update']);
    $target = DocumentLayout::factory()->create(['code' => 'original_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['code' => 'changed_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
    $this->patchJson("/api/document-layouts/{$target->id}", ['code' => 'original_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('document_layouts', ['id' => $target->id, 'code' => 'original_code']);
});

it('update: 422 when module is submitted, with a DIFFERENT or the SAME value (AC-020)', function () {
    $actor = documentLayoutUserWith(['update']);
    $target = DocumentLayout::factory()->create(['module' => 'quotes']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['module' => 'quotes'])
        ->assertStatus(422)->assertJsonValidationErrors('module');
    $this->patchJson("/api/document-layouts/{$target->id}", ['module' => 'invoices'])
        ->assertStatus(422)->assertJsonValidationErrors('module');

    $this->assertDatabaseHas('document_layouts', ['id' => $target->id, 'module' => 'quotes']);
});

it('update: 422 on code/module even for the privileged super-admin role (AC-021)', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $target = DocumentLayout::factory()->create(['code' => 'locked_code', 'module' => 'quotes']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['code' => 'attempted_change'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
    $this->patchJson("/api/document-layouts/{$target->id}", ['module' => 'quotes'])
        ->assertStatus(422)->assertJsonValidationErrors('module');

    $this->assertDatabaseHas('document_layouts', ['id' => $target->id, 'code' => 'locked_code']);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/document-layouts/{documentLayout} (AC-025, AC-028)
// ---------------------------------------------------------------------------

it('delete: 204 + removed from DB for a non-default layout (AC-025)', function () {
    $actor = documentLayoutUserWith(['delete', 'create']);
    // A non-default layout requires a sibling default in the same module —
    // create the default first (D-7a auto-assigns it), then a second one.
    DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/document-layouts/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('document_layouts', ['id' => $target->id]);
});

it('delete: 404 for a non-existent id (AC-025)', function () {
    $actor = documentLayoutUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/document-layouts/999999')->assertNotFound();
});

it('delete: removes the layout\'s attachment rows AND their binaries from disk (AC-028)', function () {
    Storage::fake('local');
    $actor = documentLayoutUserWith(['delete']);
    $target = DocumentLayout::factory()->create();

    $paths = [];

    foreach ([1, 2] as $index) {
        $path = "attachments/image-{$index}.png";
        Storage::disk('local')->put($path, 'binary-content');
        $attachment = Attachment::factory()->create([
            'collection' => DocumentLayout::IMAGE_COLLECTION,
            'disk' => 'local', 'path' => $path, 'mime_type' => 'image/png', 'extension' => 'png',
        ]);
        $attachment->attachable()->associate($target)->save();
        $paths[] = $path;
    }

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/document-layouts/{$target->id}")->assertNoContent();

    expect(Attachment::where('attachable_id', $target->id)->count())->toBe(0);

    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
});
