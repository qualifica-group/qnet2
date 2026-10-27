<?php

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * `opportunities.viewDocuments` (OpportunityPolicy) gates the `view_documents`
 * action surfaced on the opportunity detail's `permissions.actions` block —
 * the frontend gates the Opportunity documents section (reused polymorphic
 * Attachment subsystem) on it, mirroring `view_activity`/`viewActivity`.
 */
if (! function_exists('opportunityDocumentsActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityDocumentsActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        $user->givePermissionTo(Permission::findOrCreate('opportunities.viewAll', 'web'));

        return $user;
    }
}

it('permissions:sync creates opportunities.viewDocuments', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'opportunities.viewDocuments')->exists())->toBeTrue();
});

it('GET /api/opportunities/{id}: permissions.actions.view_documents is true with opportunities.viewDocuments', function () {
    $actor = opportunityDocumentsActorWith(['view', 'viewDocuments']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_documents', true);
});

it('GET /api/opportunities/{id}: permissions.actions.view_documents is false without opportunities.viewDocuments', function () {
    $actor = opportunityDocumentsActorWith(['view']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_documents', false);
});
