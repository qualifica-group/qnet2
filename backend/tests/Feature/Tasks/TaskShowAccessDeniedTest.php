<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET /api/tasks/{task} 403 access_contacts (spec 0155, D-7, AC-009)
|--------------------------------------------------------------------------
|
| REQUIREMENT CHANGED from spec 0116: the 403 is no longer a generic,
| logged/Teams-alerted incident — it carries `errors.access_contacts`
| (requester + creator, deduplicated, skipping a null requester) and is
| never logged. A missing Task still plain 404s.
*/

if (! function_exists('taskAccessDeniedActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskAccessDeniedActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        return $user;
    }
}

it('AC-009: a 403 on the detail carries requester + creator, deduplicated', function () {
    $actor = taskAccessDeniedActorWith(['view']);
    $requester = User::factory()->create(['name' => 'Rita Richiedente', 'email' => 'rita@example.test']);
    $creator = User::factory()->create(['name' => 'Carlo Creatore', 'email' => 'carlo@example.test']);
    $task = Task::factory()->forCreator($creator)->create(['requester_id' => $requester->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/tasks/{$task->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false);

    $contacts = $response->json('errors.access_contacts');
    expect($contacts)->toHaveCount(2)
        ->and(collect($contacts)->pluck('id')->sort()->values()->all())->toBe(collect([$requester->id, $creator->id])->sort()->values()->all())
        ->and(collect($contacts)->pluck('email')->all())->toContain('rita@example.test', 'carlo@example.test');
});

it('AC-009: requester and creator are deduplicated when they are the same person', function () {
    $actor = taskAccessDeniedActorWith(['view']);
    $creator = User::factory()->create();
    $task = Task::factory()->forCreator($creator)->create(['requester_id' => $creator->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/tasks/{$task->id}")->assertForbidden();

    expect($response->json('errors.access_contacts'))->toHaveCount(1);
});

it('AC-009: a null requester is skipped, only the creator is listed', function () {
    $actor = taskAccessDeniedActorWith(['view']);
    $creator = User::factory()->create();
    $task = Task::factory()->forCreator($creator)->create(['requester_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/tasks/{$task->id}")->assertForbidden();

    $contacts = $response->json('errors.access_contacts');
    expect($contacts)->toHaveCount(1)
        ->and($contacts[0]['id'])->toBe($creator->id);
});

it('AC-009: the 403 is never logged nor sent to Teams', function () {
    Log::spy();
    $actor = taskAccessDeniedActorWith(['view']);
    $task = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();

    Log::shouldNotHaveReceived('error');
});

it('a missing task still plain 404s, no access_contacts', function () {
    $actor = taskAccessDeniedActorWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tasks/999999')->assertNotFound();
});

it('without tasks.view the 403 still carries access_contacts', function () {
    $actor = User::factory()->create();
    $task = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertForbidden()
        ->assertJsonStructure(['errors' => ['access_contacts']]);
});
