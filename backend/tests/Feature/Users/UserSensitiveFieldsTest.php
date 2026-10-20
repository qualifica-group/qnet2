<?php

use App\Models\EmploymentContract;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * An actor whose only role reads and writes users, with the spec 0283 D-2
 * fields either free (no row), hidden, or visible but locked.
 *
 * @param  'free'|'hidden'|'readonly'  $access
 */
function sensitiveFieldsActor(string $access): User
{
    $permissions = ['users.viewAny', 'users.view', 'users.create', 'users.update'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('sensitive-'.Str::random(8), 'web');
    $role->givePermissionTo($permissions);

    if ($access !== 'free') {
        foreach (HrOfficeRoleCatalogue::SENSITIVE_FIELDS['users'] as $field) {
            $role->fieldPermissions()->create([
                'resource' => 'users', 'field' => $field, 'visible' => $access === 'readonly', 'editable' => false, 'required' => false,
            ]);
        }
    }

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

function sensitiveFieldsTarget(): User
{
    $target = User::factory()->withEmployment(fn ($factory) => $factory->state([
        'hired_at' => '2020-01-01', 'terminated_at' => '2030-12-31', 'standard_daily_minutes' => 480, 'break_daily_minutes' => 60,
    ]))->create();

    EmploymentContract::factory()->create(['employment_profile_id' => $target->employment->id, 'starts_on' => '2020-01-01']);

    return $target;
}

const SENSITIVE_EMPLOYMENT_KEYS = [
    'relationship_type', 'company_id', 'current_contract', 'hired_at', 'terminated_at', 'standard_daily_minutes', 'break_daily_minutes',
];

it('0283 AC-005: the user record leaves out the contractual data a hidden field covers', function () {
    $target = sensitiveFieldsTarget();

    Sanctum::actingAs(sensitiveFieldsActor('hidden'));
    $hidden = $this->getJson("/api/users/{$target->id}")->assertOk()->json('data.employment');

    Sanctum::actingAs(sensitiveFieldsActor('free'));
    $free = $this->getJson("/api/users/{$target->id}")->assertOk()->json('data.employment');

    expect($hidden)->not->toHaveKeys(SENSITIVE_EMPLOYMENT_KEYS)->toHaveKey('reports_to_ids')
        ->and($free)->toHaveKeys(SENSITIVE_EMPLOYMENT_KEYS)
        ->and($free['hired_at'])->toStartWith('2020-01-01');
});

it('0283 AC-006: the contract history answers 403 when its field is hidden, and refuses writes when locked', function () {
    $target = sensitiveFieldsTarget();
    $contract = $target->employment->contracts()->firstOrFail();
    $payload = ['starts_on' => '2026-01-01', 'relationship_type' => 'employee'];

    Sanctum::actingAs(sensitiveFieldsActor('hidden'));
    $this->getJson("/api/users/{$target->id}/employment-contracts")->assertForbidden();
    $this->postJson("/api/users/{$target->id}/employment-contracts", $payload)->assertForbidden();
    $this->deleteJson("/api/users/{$target->id}/employment-contracts/{$contract->id}")->assertForbidden();

    Sanctum::actingAs(sensitiveFieldsActor('readonly'));
    $this->getJson("/api/users/{$target->id}/employment-contracts")->assertOk()->assertJsonCount(1, 'data');
    $this->postJson("/api/users/{$target->id}/employment-contracts", $payload)->assertForbidden();
    $this->deleteJson("/api/users/{$target->id}/employment-contracts/{$contract->id}")->assertForbidden();

    expect($target->employment->contracts()->count())->toBe(1);
});

it('0283 AC-006: the field catalogue exposes employment.contracts, editable with users.update', function () {
    Sanctum::actingAs(sensitiveFieldsActor('free'));
    $target = sensitiveFieldsTarget();

    $fields = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields');

    expect($fields['employment.contracts'])->toMatchArray(['visible' => true, 'editable' => true]);
});

it('0283 AC-007: the users grid drops the contractual columns, filters and values a hidden field covers', function () {
    sensitiveFieldsTarget();
    $sensitiveColumns = ['company', 'relationship_type', 'hired_at', 'terminated_at', 'employment_status'];

    Sanctum::actingAs(sensitiveFieldsActor('hidden'));
    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id');
    $row = $this->postJson('/api/tables/users/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items.0');

    expect($columns)->not->toContain(...$sensitiveColumns)->toContain('reports_to')
        ->and($row)->not->toHaveKeys($sensitiveColumns);
    $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 50, 'filterModel' => ['employment_status' => ['values' => ['terminated']]],
    ])->assertUnprocessable();

    Sanctum::actingAs(sensitiveFieldsActor('free'));
    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($columns)->toContain(...$sensitiveColumns);
});

it('0283 AC-008: saving the employment without the contract data keeps it, null clears it', function () {
    Sanctum::actingAs(sensitiveFieldsActor('free'));
    $target = sensitiveFieldsTarget();
    $employment = ['covers_all_product_categories' => true, 'business_function_ids' => [membershipFunctionId()]];

    $this->patchJson("/api/users/{$target->id}", ['employment' => $employment])->assertOk();

    expect($target->employment->fresh())
        ->hired_at->toDateString()->toBe('2020-01-01')
        ->terminated_at->toDateString()->toBe('2030-12-31')
        ->standard_daily_minutes->toBe(480)
        ->break_daily_minutes->toBe(60);

    $this->patchJson("/api/users/{$target->id}", ['employment' => [...$employment, 'terminated_at' => null, 'break_daily_minutes' => null]])->assertOk();

    expect($target->employment->fresh())
        ->hired_at->toDateString()->toBe('2020-01-01')
        ->terminated_at->toBeNull()
        ->break_daily_minutes->toBeNull();
});
