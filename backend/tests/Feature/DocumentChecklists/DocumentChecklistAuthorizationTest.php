<?php

use App\Models\DocumentChecklist;
use App\Models\User;
use App\Policies\DocumentChecklistPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
| Authorization and menu (spec 0403): AC-007 (resource endpoints and the
| manageConnection ability), AC-022.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('AC-007: every endpoint of the resource answers 403 without the permission', function (string $method, string $uri) {
    Sanctum::actingAs(documentChecklistUserWith([]));
    $checklist = DocumentChecklist::factory()->create();

    $response = $this->json($method, str_replace('{id}', (string) $checklist->id, $uri), documentChecklistPayload([
        'code' => 'ALTRO',
        'is_active' => true,
    ]));

    $response->assertForbidden();
    Queue::assertNothingPushed();
})->with([
    'show' => ['GET', '/api/document-checklists/{id}'],
    'store' => ['POST', '/api/document-checklists'],
    'update' => ['PUT', '/api/document-checklists/{id}'],
    'activation' => ['PATCH', '/api/document-checklists/{id}/activation'],
    'destroy' => ['DELETE', '/api/document-checklists/{id}'],
    'sync' => ['POST', '/api/document-checklists/{id}/sync'],
    'sync-all' => ['POST', '/api/document-checklists/sync-all'],
    'sync-logs' => ['GET', '/api/document-checklists/{id}/sync-logs'],
]);

it('AC-007: the grid answers 403 without viewAny', function () {
    Sanctum::actingAs(documentChecklistUserWith([]));

    $this->postJson('/api/tables/document-checklists/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('AC-007: update-only users cannot delete, view-only users cannot write', function () {
    $checklist = DocumentChecklist::factory()->create();

    Sanctum::actingAs(documentChecklistUserWith(['update']));
    $this->deleteJson("/api/document-checklists/{$checklist->id}")->assertForbidden();

    Sanctum::actingAs(documentChecklistUserWith(['view']));
    $this->postJson("/api/document-checklists/{$checklist->id}/sync")->assertForbidden();
    $this->postJson('/api/document-checklists/sync-all')->assertForbidden();
    Queue::assertNothingPushed();
});

it('AC-007: manageConnection is a separate ability of the policy', function () {
    $policy = new DocumentChecklistPolicy;

    $everythingButConnection = documentChecklistUserWith(['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity']);
    expect($policy->manageConnection($everythingButConnection))->toBeFalse();

    $manager = documentChecklistUserWith(['manageConnection']);
    expect($policy->manageConnection($manager))->toBeTrue()
        ->and(DocumentChecklistPolicy::abilities())->toContain('manageConnection')
        ->not->toContain('export', 'import');
});

it('AC-022: the menu entry shows under config-documents only with document-checklists.view', function () {
    Permission::findOrCreate('document-checklists.view');

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('document-checklists');

    $withView = User::factory()->create();
    $withView->givePermissionTo('document-checklists.view');
    Sanctum::actingAs($withView);

    $node = collect($this->getJson('/api/navigation')->json('data'))
        ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])])
        ->flatMap(fn (array $item) => [$item, ...($item['children'] ?? [])])
        ->firstWhere('key', 'document-checklists');

    expect($node)->not->toBeNull()
        ->and($node['route'])->toBe('/document-checklists')
        ->and($node['icon'])->toBe('list-checks');
});

it('AC-022: permissions:sync creates the document-checklists permissions, manageConnection included', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity', 'manageConnection'] as $ability) {
        expect(Permission::where('name', "document-checklists.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'document-checklists.export')->exists())->toBeFalse();
});
