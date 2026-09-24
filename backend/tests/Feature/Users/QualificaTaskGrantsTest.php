<?php

use App\Models\Task;
use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaOperatorSeeder;
use Database\Seeders\QualificaStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Moved out of QualificaRoleMatrixTest (file size limit) when the base role of
// the staff joined the catalogue (user directive 2026-09-24).
uses(RefreshDatabase::class);

// User directive 2026-09-24: every mansione works the Tasks it takes part in
// and its own segnatempo — never everyone's.
it('opens own tasks and own time entries to every role, never the wider scopes', function () {
    // The base role's accounts are QualificaStaffSeeder's, not the roster's.
    $this->seed(QualificaOperatorSeeder::class);
    $this->seed(QualificaStaffSeeder::class);

    foreach (OperatorRoleCatalogue::ROLES as $name => $role) {
        $user = User::role($name)->firstOrFail();

        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'complete', 'viewDocuments', 'requestUpdate'] as $ability) {
            expect($user->can("tasks.{$ability}"))->toBeTrue("{$name} tasks.{$ability}");
        }

        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($user->can("time-entries.{$ability}"))->toBeTrue("{$name} time-entries.{$ability}");
        }

        foreach (OperatorRoleCatalogue::OWN_TASKS_DENIED_ABILITIES as $ability) {
            expect($user->can("tasks.{$ability}"))->toBeFalse("{$name} tasks.{$ability}");
        }

        foreach (OperatorRoleCatalogue::OWN_TIME_ENTRIES_DENIED_ABILITIES as $ability) {
            expect($user->can("time-entries.{$ability}"))->toBeFalse("{$name} time-entries.{$ability}");
        }

        expect($user->can('notes.create'))->toBeTrue("{$name} notes.create")
            ->and($user->can('attachments.create'))->toBeTrue("{$name} attachments.create")
            ->and(visibleRoutes($user))->toContain('/tasks', '/time-entries')
            // The five Task configurators stay closed.
            ->and($user->can('task-statuses.view'))->toBeFalse("{$name} task-statuses.view");
    }
});

it('lists a commercial only the tasks they take part in', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $commercial = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
    $own = Task::factory()->forCreator($commercial)->create();
    $foreign = Task::factory()->create();

    Sanctum::actingAs($commercial);

    $this->getJson("/api/tasks/{$own->id}")->assertOk();
    $this->getJson("/api/tasks/{$foreign->id}")->assertForbidden();
});

// User directive 2026-09-24: the staff outside the mansionario sees Task and
// Segnatempo, nothing else.
it('shows the base role only the dashboard, tasks and time entries', function () {
    $this->seed(QualificaStaffSeeder::class);

    $staff = User::role(OperatorRoleCatalogue::BASE_ROLE)->firstOrFail();

    expect(visibleRoutes($staff))->toBe(['/dashboard', '/tasks', '/time-entries'])
        ->and($staff->can('request-management.viewAny'))->toBeFalse()
        ->and($staff->can('leads.viewAny'))->toBeFalse()
        ->and($staff->can('users.view'))->toBeFalse();

    Sanctum::actingAs($staff);

    // The Task form's assignee select answers; every other grid stays closed.
    $this->getJson('/api/users/for-select')->assertOk();
    $this->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});
