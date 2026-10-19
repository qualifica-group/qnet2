<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('0273 AC-001 / 0275 AC-015: lists every function with parent, manager and active people with roles', function () {
    $root = BusinessFunction::factory()->create(['name' => 'Sales', 'is_business_unit' => true, 'is_business_service' => false]);
    $manager = orgChartPerson('Zeta Boss');
    $child = BusinessFunction::factory()->create(['name' => 'Inbound', 'parent_id' => $root->id, 'manager_id' => $manager->id, 'is_business_unit' => false, 'is_business_service' => true]);
    $other = BusinessFunction::factory()->create(['name' => 'Empty', 'is_business_unit' => false, 'is_business_service' => false]);

    $bob = orgChartPerson('Bob');
    $anna = orgChartPerson('Anna');
    $retired = orgChartPerson('Retired');
    $retired->forceFill(['is_active' => false])->save();
    foreach ([$bob, $anna, $retired] as $person) {
        $person->businessFunctions()->attach($child->id);
    }
    // membership of a second function must not duplicate the person in the first
    $anna->businessFunctions()->attach($root->id);
    $anna->employment->jobRoles()->attach([JobRole::factory()->create(['name' => 'Zeta role'])->id, JobRole::factory()->create(['name' => 'Alpha role'])->id]);
    $unassigned = orgChartPerson('Unassigned');
    // competences alone do not make a person a member
    $unassigned->employment->productLines()->create(['business_function_id' => $child->id, 'product_category_id' => null]);

    Sanctum::actingAs(orgChartActor(['org-chart.view']));

    $data = $this->getJson('/api/org-chart/functions')->assertOk()->json('data');
    $byId = collect($data['functions'])->keyBy('id');

    expect($byId)->toHaveCount(3)
        ->and($byId[$child->id])->toMatchArray([
            'name' => 'Inbound', 'type' => 'business_service', 'parent_id' => $root->id,
            'manager' => ['id' => $manager->id, 'name' => 'Zeta Boss'],
        ])
        ->and(collect($byId[$child->id]['people'])->pluck('name')->all())->toBe(['Anna', 'Bob'])
        ->and($byId[$child->id]['people'][0])->toBe([
            'id' => $anna->id, 'name' => 'Anna', 'avatar_url' => null,
            'job_roles' => ['Alpha role', 'Zeta role'], 'is_manager' => false,
        ])
        ->and(collect($byId[$root->id]['people'])->pluck('name')->all())->toBe(['Anna'])
        ->and($byId[$root->id]['type'])->toBe('business_unit')
        ->and($byId[$other->id])->toMatchArray(['type' => null, 'parent_id' => null, 'manager' => null, 'people' => []])
        ->and($data)->not->toHaveKey('all_functions_people')
        ->and(collect($data['unassigned_people'])->pluck('name'))->toContain('Unassigned', 'Zeta Boss')
        ->not->toContain('Anna', 'Bob', 'Retired');
});

it('0273 AC-001: functions endpoint is 403 without org-chart.view', function () {
    Sanctum::actingAs(orgChartActor(['users.view']));

    $this->getJson('/api/org-chart/functions')->assertForbidden();
});

it('0273 AC-002: lists active non-service people with manager_ids and functions', function () {
    $boss = orgChartPerson('Boss');
    $retiredBoss = orgChartPerson('Retired boss');
    $retiredBoss->forceFill(['is_active' => false])->save();
    $worker = orgChartPerson('Worker', [], $boss, $retiredBoss);
    $function = BusinessFunction::factory()->create(['name' => 'Ops']);
    $worker->businessFunctions()->attach($function->id);
    User::factory()->create(['name' => 'Api client', 'is_active' => true, 'is_service_account' => true]);
    User::factory()->create(['name' => 'No profile', 'is_active' => true]);

    $viewer = orgChartActor(['org-chart.view']);
    $viewer->forceFill(['name' => 'Viewer'])->save();
    Sanctum::actingAs($viewer);

    $data = collect($this->getJson('/api/org-chart/people')->assertOk()->json('data'));

    expect($data->pluck('name')->all())->toBe(['Boss', 'No profile', 'Viewer', 'Worker'])
        ->and($data->firstWhere('name', 'Worker'))->toMatchArray([
            'manager_ids' => [$boss->id], 'business_functions' => [['id' => $function->id, 'name' => 'Ops', 'is_manager' => false]], 'is_manager' => false,
        ])
        ->and($data->firstWhere('name', 'Boss')['manager_ids'])->toBe([])
        ->and($data->firstWhere('name', 'No profile')['business_functions'])->toBe([]);
});

it('0273 AC-002: people endpoint is 403 without org-chart.view', function () {
    Sanctum::actingAs(orgChartActor([]));

    $this->getJson('/api/org-chart/people')->assertForbidden();
});

it('0273 AC-001/AC-002: both reads use a fixed number of queries with lazy loading prevented', function (string $path) {
    $function = BusinessFunction::factory()->create();
    $boss = orgChartPerson('Boss');
    foreach (range(1, 30) as $i) {
        $person = orgChartPerson("Person {$i}", [], $boss);
        $person->employment->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => null]);
        $person->employment->jobRoles()->attach(JobRole::factory()->create()->id);
    }
    Sanctum::actingAs(orgChartActor(['org-chart.view']));
    $this->getJson($path)->assertOk(); // warm: permission cache

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->getJson($path)->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')->reject(fn (string $sql): bool => str_contains($sql, 'personal_access_tokens'));

    expect($queries->count())->toBeLessThanOrEqual(8, $queries->implode("\n"))
        ->and(EmploymentProfile::count())->toBe(31)
        ->and(count($response->json('data.functions') ?? $response->json('data')))->toBeGreaterThan(0);
})->with(['/api/org-chart/functions', '/api/org-chart/people']);

it('0275 AC-022: people view reads functions from the membership as {id,name,is_manager}, ignoring competences', function () {
    $alpha = BusinessFunction::factory()->create(['name' => 'Alpha']);
    $beta = BusinessFunction::factory()->create(['name' => 'Beta']);
    $competenceOnly = BusinessFunction::factory()->create(['name' => 'Gamma']);
    $lead = orgChartPerson('Lead');
    $alpha->update(['manager_id' => $lead->id]);
    $beta->update(['manager_id' => $lead->id]);
    $lead->businessFunctions()->attach([$beta->id, $alpha->id]);
    $lead->employment->productLines()->create(['business_function_id' => $competenceOnly->id, 'product_category_id' => null]);
    $member = orgChartPerson('Member');
    $member->businessFunctions()->attach($alpha->id);
    Sanctum::actingAs(orgChartActor(['org-chart.view']));

    $data = collect($this->getJson('/api/org-chart/people')->assertOk()->json('data'));

    expect($data->firstWhere('name', 'Lead')['business_functions'])->toBe([
        ['id' => $alpha->id, 'name' => 'Alpha', 'is_manager' => true],
        ['id' => $beta->id, 'name' => 'Beta', 'is_manager' => true],
    ])->and($data->firstWhere('name', 'Member')['business_functions'])->toBe([
        ['id' => $alpha->id, 'name' => 'Alpha', 'is_manager' => false],
    ]);
});

it('0276 AC-010: Person.is_manager is true only for who manages at least one function', function (string $path) {
    $manager = orgChartPerson('Manager');
    $plain = orgChartPerson('Plain');
    BusinessFunction::factory()->count(2)->create(['manager_id' => $manager->id]);
    Sanctum::actingAs(orgChartActor(['org-chart.view']));

    $people = $path === '/api/org-chart/people'
        ? collect($this->getJson($path)->assertOk()->json('data'))
        : collect($this->getJson($path)->assertOk()->json('data.unassigned_people'));

    expect($people->firstWhere('id', $manager->id)['is_manager'])->toBeTrue()
        ->and($people->firstWhere('id', $plain->id)['is_manager'])->toBeFalse();
})->with(['/api/org-chart/people', '/api/org-chart/functions']);

it('0275 follow-up: a function manager is shown only when active and not a service account', function () {
    $active = orgChartPerson('Active Boss');
    $inactive = orgChartPerson('Retired Boss');
    $inactive->forceFill(['is_active' => false])->save();
    $service = User::factory()->create(['is_active' => true, 'is_service_account' => true]);
    $withActive = BusinessFunction::factory()->create(['manager_id' => $active->id]);
    $withInactive = BusinessFunction::factory()->create(['manager_id' => $inactive->id]);
    $withService = BusinessFunction::factory()->create(['manager_id' => $service->id]);
    Sanctum::actingAs(orgChartActor(['org-chart.view']));

    $byId = collect($this->getJson('/api/org-chart/functions')->assertOk()->json('data.functions'))->keyBy('id');

    expect($byId[$withActive->id]['manager'])->toBe(['id' => $active->id, 'name' => 'Active Boss'])
        ->and($byId[$withInactive->id]['manager'])->toBeNull()
        ->and($byId[$withService->id]['manager'])->toBeNull();
});
