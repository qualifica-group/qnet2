<?php

use App\Models\DocumentLayout;
use App\Models\User;
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
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/document-layouts/for-select?module=quotes')->assertUnauthorized();
});

it('allows actors without document-layouts.viewAny (200 — ADR 0011 amended)', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/for-select?module=quotes')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-080 — mapping shape, default first
// ---------------------------------------------------------------------------

it('maps a document layout to { id, label: name, subtitle: code, meta: { is_default, code } } (AC-080)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    $target = DocumentLayout::factory()->create(['name' => 'Layout Standard', 'code' => 'layout_standard', 'module' => 'quotes', 'is_default' => true]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/document-layouts/for-select?module=quotes')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label', 'subtitle', 'meta' => ['is_default', 'code']]],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);

    $item = collect($response->json('items'))->firstWhere('id', $target->id);
    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Layout Standard', 'subtitle' => 'layout_standard', 'meta' => ['is_default' => true, 'code' => 'layout_standard']]);
});

it('the default layout of the module is the FIRST item (AC-080)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    $nonDefault = DocumentLayout::factory()->create(['name' => 'Alpha', 'module' => 'quotes', 'is_default' => false]);
    $default = DocumentLayout::factory()->create(['name' => 'Zeta', 'module' => 'quotes', 'is_default' => true]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/document-layouts/for-select?module=quotes')->assertOk();

    expect($response->json('items.0.id'))->toBe($default->id);
    expect(collect($response->json('items'))->pluck('id'))->toContain($nonDefault->id);
});

// ---------------------------------------------------------------------------
// AC-081 — active-only, module-scoped
// ---------------------------------------------------------------------------

it('excludes inactive layouts (AC-081)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    $active = DocumentLayout::factory()->create(['name' => 'Active One', 'module' => 'quotes', 'is_active' => true]);
    $inactive = DocumentLayout::factory()->create(['name' => 'Inactive One', 'module' => 'quotes', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/document-layouts/for-select?module=quotes')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($active->id)->and($ids)->not->toContain($inactive->id);
});

// ---------------------------------------------------------------------------
// AC-082 — ids[] hydration even for inactive, does not inflate total
// ---------------------------------------------------------------------------

it('hydrates ids[] even when the layout is inactive, and does not inflate total (AC-082)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    $inactive = DocumentLayout::factory()->create(['name' => 'Deactivated One', 'module' => 'quotes', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/document-layouts/for-select?module=quotes&ids[]={$inactive->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($inactive->id)
        ->and($response->json('pagination.total'))->toBe(0);
});

it('search="appr" returns only names containing "appr" (search filter)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    $match = DocumentLayout::factory()->create(['name' => 'Approved Layout', 'module' => 'quotes']);
    DocumentLayout::factory()->create(['name' => 'Other Layout', 'module' => 'quotes']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/document-layouts/for-select?module=quotes&search=appr')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-083 — module required, limit cap
// ---------------------------------------------------------------------------

it('rejects a request without `module` (422, AC-083)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/for-select')
        ->assertStatus(422)->assertJsonValidationErrors('module');
});

it('rejects a limit above 100 (422, AC-083)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/for-select?module=quotes&limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
