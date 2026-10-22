<?php

use App\Models\AbsenceType;
use App\Models\CompanyClosure;
use App\Models\OperationalSite;
use Database\Seeders\QualificaRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-22 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

function closurePayload(array $overrides = []): array
{
    return [
        'name' => 'Bridge',
        'starts_on' => '2026-12-07',
        'ends_on' => '2026-12-07',
        'kind' => 'holiday',
        'applies_to_all_sites' => true,
        ...$overrides,
    ];
}

it('0304 AC-014: every endpoint answers 403 without the permission', function () {
    Sanctum::actingAs(hrCatalogUserWith('company-closures', []));
    $closure = CompanyClosure::factory()->create();

    $this->getJson("/api/company-closures/{$closure->id}")->assertForbidden();
    $this->postJson('/api/company-closures', closurePayload())->assertForbidden();
    $this->putJson("/api/company-closures/{$closure->id}", closurePayload())->assertForbidden();
    $this->deleteJson("/api/company-closures/{$closure->id}")->assertForbidden();
    $this->postJson('/api/tables/company-closures/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0304 AC-014: create, show and update answer with the contract shape and the permissions block', function () {
    Sanctum::actingAs(hrCatalogUserWith('company-closures', ['create', 'view', 'update']));
    $site = OperationalSite::factory()->create();

    $id = $this->postJson('/api/company-closures', closurePayload(['applies_to_all_sites' => false, 'operational_site_ids' => [$site->id], 'repeats_yearly' => true]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bridge')
        ->assertJsonPath('data.kind', 'holiday')
        ->assertJsonPath('data.absence_type', null)
        ->assertJsonPath('data.operational_sites.0.id', $site->id)
        ->assertJsonPath('data.repeats_yearly', true)
        ->assertJsonPath('data.is_started', false)
        ->assertJsonPath('data.generated_requests_count', 0)
        ->assertJsonMissingPath('data.generation')
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'starts_on', 'ends_on', 'applies_to_all_sites', 'created_at', 'updated_at'], 'permissions'])
        ->json('data.id');

    $this->getJson("/api/company-closures/{$id}")->assertOk()->assertJsonStructure(['permissions']);
    $this->putJson("/api/company-closures/{$id}", closurePayload(['name' => 'Patron', 'applies_to_all_sites' => true]))
        ->assertOk()->assertJsonPath('data.name', 'Patron')->assertJsonPath('data.operational_sites', []);
    expect(CompanyClosure::find($id)->created_by)->not->toBeNull();
});

it('0304 AC-014: validation of absence type, sites, yearly repeat and length', function () {
    Sanctum::actingAs(hrCatalogUserWith('company-closures', ['create']));
    $type = AbsenceType::factory()->create();
    $inactive = AbsenceType::factory()->inactive()->create();

    $this->postJson('/api/company-closures', closurePayload(['absence_type_id' => $type->id]))->assertStatus(422)->assertJsonValidationErrors('absence_type_id');
    $this->postJson('/api/company-closures', closurePayload(['kind' => 'collective_leave']))->assertStatus(422)->assertJsonValidationErrors('absence_type_id');
    $this->postJson('/api/company-closures', closurePayload(['kind' => 'collective_leave', 'absence_type_id' => $inactive->id]))->assertStatus(422)->assertJsonValidationErrors('absence_type_id');
    $this->postJson('/api/company-closures', closurePayload(['applies_to_all_sites' => false]))->assertStatus(422)->assertJsonValidationErrors('operational_site_ids');
    $this->postJson('/api/company-closures', closurePayload(['applies_to_all_sites' => false, 'operational_site_ids' => []]))->assertStatus(422)->assertJsonValidationErrors('operational_site_ids');
    $this->postJson('/api/company-closures', closurePayload(['applies_to_all_sites' => false, 'operational_site_ids' => [999999]]))->assertStatus(422)->assertJsonValidationErrors('operational_site_ids.0');
    $this->postJson('/api/company-closures', closurePayload(['ends_on' => '2026-12-09', 'repeats_yearly' => true]))->assertStatus(422)->assertJsonValidationErrors('repeats_yearly');
    $this->postJson('/api/company-closures', closurePayload(['kind' => 'collective_leave', 'absence_type_id' => $type->id, 'repeats_yearly' => true]))->assertStatus(422)->assertJsonValidationErrors('repeats_yearly');
    $this->postJson('/api/company-closures', closurePayload(['ends_on' => '2027-01-08']))->assertStatus(422)->assertJsonValidationErrors('ends_on');
    $this->postJson('/api/company-closures', closurePayload(['ends_on' => '2026-12-01']))->assertStatus(422)->assertJsonValidationErrors('ends_on');
    $this->postJson('/api/company-closures', closurePayload(['name' => '', 'kind' => 'x']))->assertStatus(422)->assertJsonValidationErrors(['name', 'kind']);

    $this->postJson('/api/company-closures', closurePayload(['ends_on' => '2027-01-06']))->assertCreated();
});

it('0304 AC-014: a started closure answers 409 on update and delete, a yearly one does not', function () {
    Sanctum::actingAs(hrCatalogUserWith('company-closures', ['update', 'delete']));
    $started = CompanyClosure::factory()->create(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-23']);
    $yearly = CompanyClosure::factory()->yearly()->create(['starts_on' => '2026-06-02', 'ends_on' => '2026-06-02']);
    $future = CompanyClosure::factory()->create(['starts_on' => '2026-12-07', 'ends_on' => '2026-12-07']);

    $this->putJson("/api/company-closures/{$started->id}", closurePayload(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-01']))->assertStatus(409);
    $this->deleteJson("/api/company-closures/{$started->id}")->assertStatus(409);
    $this->putJson("/api/company-closures/{$yearly->id}", closurePayload(['name' => 'Patron', 'starts_on' => '2026-06-02', 'ends_on' => '2026-06-02', 'repeats_yearly' => true]))
        ->assertOk()->assertJsonPath('data.is_started', true);
    $this->deleteJson("/api/company-closures/{$yearly->id}")->assertNoContent();
    $this->deleteJson("/api/company-closures/{$future->id}")->assertNoContent();

    expect(CompanyClosure::find($started->id))->not->toBeNull()
        ->and(CompanyClosure::find($future->id))->toBeNull();
});

it('0304 AC-014: the grid lists the closures with sites, type and generated count', function () {
    Sanctum::actingAs(hrCatalogUserWith('company-closures', ['viewAny', 'update']));
    $type = AbsenceType::factory()->create(['name' => 'Ferie']);
    $site = OperationalSite::factory()->create(['alias' => 'Rome HQ']);
    CompanyClosure::factory()->collectiveLeave($type)->create(['name' => 'August', 'applies_to_all_sites' => false])->operationalSites()->attach($site->id);
    CompanyClosure::factory()->create(['name' => 'Bridge']);

    $columns = collect($this->getJson('/api/tables/company-closures/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $rows = collect($this->postJson('/api/tables/company-closures/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->keyBy('name');

    expect($columns)->toContain('name', 'starts_on', 'ends_on', 'kind', 'absence_type_name', 'sites', 'repeats_yearly', 'generated_requests_count')
        ->and($rows['August']['sites'])->toBe('Rome HQ')
        ->and($rows['August']['absence_type_name'])->toBe('Ferie')
        ->and($rows['August']['kind'])->toBe('collective_leave')
        ->and($rows['Bridge']['sites'])->toBe(__('company_closures.all_sites'))
        ->and($rows['Bridge']['generated_requests_count'])->toBe(0);
});

it('0304 AC-014: the HR office role is seeded with every company closure ability', function () {
    $this->seed(QualificaRoleSeeder::class);
    $role = Role::findByName('risorse-umane');

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        expect($role->hasPermissionTo("company-closures.{$ability}"))->toBeTrue($ability);
    }
});

it('0304 AC-014: the closure shows up in the navigation for who can view it', function () {
    Sanctum::actingAs(hrCatalogUserWith('company-closures', ['view']));

    $keys = collect($this->getJson('/api/navigation')->assertOk()->json('data'))->flatten(3)->all();

    expect(json_encode($keys))->toContain('company-closures');
});
