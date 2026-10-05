<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Services\Assignment\OperatorCompetence;
use App\Services\LeadOperatorDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0194 — the "Assegnabile" switch on the employment profile: off takes
 * the user out of every assignment pool, whatever their Sedi and competence.
 */
if (! function_exists('assignableSwitchActor')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function assignableSwitchActor(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $role = Role::create(['name' => 'assignable-switch-role-'.uniqid()]);
        $role->givePermissionTo($permissions);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

it('AC-001: a profile is assignable by default', function (): void {
    expect(EmploymentProfile::factory()->create()->fresh()->is_assignable)->toBeTrue();
});

it('AC-002: a switched-off member is in no Sede pool', function (): void {
    $site = OperationalSite::factory()->create();
    $assignable = EmploymentProfile::factory()->physicalSite($site)->coversAllProductCategories()->create();
    $switchedOff = EmploymentProfile::factory()->physicalSite($site)->coversAllProductCategories()->notAssignable()->create();

    $pool = app(LeadOperatorDistributor::class)->operatorIdsBySite([$site->id])[$site->id];

    expect($pool)->toContain($assignable->user_id)
        ->and($pool)->not->toContain($switchedOff->user_id);
});

it('AC-003: a switched-off user is never competent, even with the wildcard or a matching row', function (): void {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $competent = EmploymentProfile::factory()->competentIn($function, $category)->create();
    $wildcardOff = EmploymentProfile::factory()->coversAllProductCategories()->notAssignable()->create();
    $rowOff = EmploymentProfile::factory()->competentIn($function, $category)->notAssignable()->create();

    expect(app(OperatorCompetence::class)->competentUserIds([$category->id]))
        ->toBe([$competent->user_id])
        ->not->toContain($wildcardOff->user_id)
        ->not->toContain($rowOff->user_id);
});

it('AC-004: the Sede-scoped for-select drops a switched-off member; the unscoped one still lists them', function (): void {
    $actor = assignableSwitchActor(['users.viewAny']);
    $site = OperationalSite::factory()->withAddress()->create();
    $assignable = EmploymentProfile::factory()->physicalSite($site)->create();
    $switchedOff = EmploymentProfile::factory()->physicalSite($site)->notAssignable()->create();
    Sanctum::actingAs($actor);

    $scoped = collect($this->getJson("/api/users/for-select?operational_site_id={$site->id}")->assertOk()->json('items'))->pluck('id');
    $unscoped = collect($this->getJson('/api/users/for-select?per_page=100')->assertOk()->json('items'))->pluck('id');

    expect($scoped)->toContain($assignable->user_id)
        ->and($scoped)->not->toContain($switchedOff->user_id)
        ->and($unscoped)->toContain($switchedOff->user_id);
});

it('AC-005: an offer with no Sede and no category refuses a switched-off operator', function (): void {
    $actor = assignableSwitchActor(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);
    $quote = Quote::factory()->for(Opportunity::factory())->create(['operator_id' => $actor->id]);
    $switchedOff = EmploymentProfile::factory()->notAssignable()->create();
    $assignable = EmploymentProfile::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => $switchedOff->user_id,
    ])->assertStatus(422);

    expect($quote->fresh()->operator_id)->toBe($actor->id);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => $assignable->user_id,
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($assignable->user_id);
});

it('AC-006: the switch is written when sent and left untouched when omitted', function (): void {
    $actor = assignableSwitchActor(['users.viewAny', 'users.view', 'users.update']);
    $target = EmploymentProfile::factory()->create(['job_description' => 'Commerciale'])->user;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['is_assignable' => false]])
        ->assertOk()
        ->assertJsonPath('data.employment.is_assignable', false);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['job_description' => 'Supervisor']])->assertOk();

    expect($target->fresh()->employment->is_assignable)->toBeFalse();

    $this->getJson("/api/users/{$target->id}")->assertOk()
        ->assertJsonPath('data.employment.is_assignable', false);
});

it('AC-006: the switch must be a boolean', function (): void {
    $actor = assignableSwitchActor(['users.viewAny', 'users.view', 'users.update']);
    $target = EmploymentProfile::factory()->create()->user;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['is_assignable' => 'maybe']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employment.is_assignable']);
});
