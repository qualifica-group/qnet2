<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A super-admin is changed, deactivated or deleted only by another
 * super-admin (directive utente 2026-10-26): every write path on the user
 * goes through UserPolicy, and the metadata never offers what it refuses.
 */
function protectedSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE, 'web'));

    return $user;
}

function fullUserManager(): User
{
    return userWithUserAbilities(['viewAny', 'view', 'create', 'update', 'delete']);
}

it('refuses edit, deactivation and delete of a super-admin to a non-super-admin', function () {
    Sanctum::actingAs(fullUserManager());
    $target = protectedSuperAdmin();

    // Every field is read-only for this actor: the field gate of the FormRequest refuses first.
    $this->patchJson("/api/users/{$target->id}", ['is_active' => false])->assertStatus(422)->assertJsonValidationErrors(['is_active']);
    $this->patchJson("/api/users/{$target->id}", ['locale' => $target->locale === 'en' ? 'it' : 'en'])->assertStatus(422)->assertJsonValidationErrors(['locale']);
    $this->deleteJson("/api/users/{$target->id}")->assertForbidden();
    $this->postJson("/api/users/{$target->id}/avatar", ['avatar' => UploadedFile::fake()->image('a.png')])->assertForbidden();
    $this->postJson("/api/users/{$target->id}/resend-welcome")->assertForbidden();

    $this->postJson('/api/users/bulk-activation', ['is_active' => false, 'ids' => [$target->id]])
        ->assertOk()
        ->assertJsonPath('data.updated', [])
        ->assertJsonPath('data.failed.0.message', __('users.bulk_activation.forbidden'));

    expect($this->postJson('/api/tables/users/bulk-delete', ['ids' => [$target->id]])->assertOk()->json('data.deleted'))->toBe(0);

    $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true, 'locale' => $target->locale]);
});

it('shows a super-admin read-only to a non-super-admin: no update, no delete, every field locked', function () {
    Sanctum::actingAs(fullUserManager());
    $target = protectedSuperAdmin();

    $response = $this->getJson("/api/users/{$target->id}")->assertOk();

    expect($response->json('permissions.resource.update'))->toBeFalse()
        ->and($response->json('permissions.resource.delete'))->toBeFalse()
        ->and($response->json('permissions.actions.delete'))->toBeFalse()
        ->and($response->json('permissions.actions.upload_avatar'))->toBeFalse()
        ->and($response->json('permissions.fields.is_active.editable'))->toBeFalse()
        ->and($response->json('permissions.fields.email.editable'))->toBeFalse()
        ->and($response->json('permissions.fields.roles.editable'))->toBeFalse();

    $rows = collect($this->postJson('/api/tables/users/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$target->id]['actions'])->not->toContain('delete');
});

it('leaves a plain user fully manageable by the same actor', function () {
    Sanctum::actingAs(fullUserManager());
    $target = User::factory()->create();

    $response = $this->getJson("/api/users/{$target->id}")->assertOk();
    expect($response->json('permissions.resource.update'))->toBeTrue()
        ->and($response->json('permissions.actions.delete'))->toBeTrue()
        ->and($response->json('permissions.fields.is_active.editable'))->toBeTrue();

    $this->patchJson("/api/users/{$target->id}", ['is_active' => false])->assertOk();
    $this->deleteJson("/api/users/{$target->id}")->assertNoContent();
});

it('lets a super-admin deactivate and delete another super-admin', function () {
    Sanctum::actingAs(protectedSuperAdmin());
    $target = protectedSuperAdmin();

    $this->postJson('/api/users/bulk-activation', ['is_active' => false, 'ids' => [$target->id]])
        ->assertOk()
        ->assertJsonPath('data.updated', [$target->id]);
    $this->deleteJson("/api/users/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});
