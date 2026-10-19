<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function organizationActor(array $abilities = ['viewAny', 'view', 'create', 'update']): User
{
    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        Permission::findOrCreate("business-functions.{$ability}");
        $user->givePermissionTo("business-functions.{$ability}");
    }

    return $user;
}

it('AC-004 create saves companies and returns them', function () {
    Sanctum::actingAs(organizationActor());
    $companies = Company::factory()->count(2)->create();

    $this->postJson('/api/business-functions', [
        'name' => 'Org',
        'company_ids' => $companies->pluck('id')->all(),
    ])->assertCreated()
        ->assertJsonCount(2, 'data.company_ids')
        ->assertJsonPath('data.companies.0.label', fn (string $label): bool => $label !== '');

    expect(BusinessFunction::firstWhere('name', 'Org')->companies)->toHaveCount(2);
});

it('AC-004 show returns empty organization by default', function () {
    Sanctum::actingAs(organizationActor());
    $bf = BusinessFunction::factory()->create();

    $this->getJson("/api/business-functions/{$bf->id}")->assertOk()
        ->assertJsonPath('data.company_ids', [])
        ->assertJsonPath('data.companies', []);
});

it('AC-004 update with absent company_ids leaves companies unchanged, an empty list detaches them', function () {
    Sanctum::actingAs(organizationActor());
    $company = Company::factory()->create();
    $bf = BusinessFunction::factory()->create();
    $bf->companies()->sync([$company->id]);

    $this->patchJson("/api/business-functions/{$bf->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.company_ids', [$company->id]);

    $this->patchJson("/api/business-functions/{$bf->id}", ['company_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.company_ids', []);
});

it('AC-004 returns 422 on non-existent or duplicated company ids', function () {
    Sanctum::actingAs(organizationActor());
    $company = Company::factory()->create();

    $this->postJson('/api/business-functions', ['name' => 'X', 'company_ids' => [999999]])
        ->assertStatus(422)->assertJsonValidationErrors('company_ids.0');
    $this->postJson('/api/business-functions', ['name' => 'X', 'company_ids' => [$company->id, $company->id]])
        ->assertStatus(422)->assertJsonValidationErrors('company_ids.0');
});

it('AC-004 returns 403 when saving the organization without update permission', function () {
    Sanctum::actingAs(organizationActor(['viewAny', 'view']));
    $bf = BusinessFunction::factory()->create();

    $this->patchJson("/api/business-functions/{$bf->id}", ['company_ids' => []])->assertForbidden();
});

it('AC-004 grid exposes companies and filters them by set', function () {
    Sanctum::actingAs(organizationActor());
    $acme = Company::factory()->create(['denomination' => 'Acme Srl']);
    $beta = Company::factory()->create(['denomination' => 'Beta Spa']);
    $a = BusinessFunction::factory()->create(['name' => 'FA']);
    $b = BusinessFunction::factory()->create(['name' => 'FB']);
    BusinessFunction::factory()->create(['name' => 'FC']);
    $a->companies()->sync([$acme->id, $beta->id]);
    $b->companies()->sync([$beta->id]);

    $rows = fn (array $body) => $this->postJson('/api/tables/business-functions/rows', $body + ['startRow' => 0, 'endRow' => 25])->assertOk();

    $row = collect($rows([])->json('items'))->firstWhere('name', 'FA');
    expect(collect($row['companies'])->pluck('label')->sort()->values()->all())->toBe(['Acme Srl', 'Beta Spa']);

    expect($rows(['filterModel' => ['companies' => ['filterType' => 'set', 'values' => ['Acme Srl']]]])->json('items.*.name'))->toBe(['FA']);
    expect($rows(['filterModel' => ['companies' => ['filterType' => 'set', 'values' => [null]]]])->json('items.*.name'))->toBe(['FC']);
});

it('AC-004 grid distinct values for companies', function () {
    Sanctum::actingAs(organizationActor());
    $acme = Company::factory()->create(['denomination' => 'Acme Srl']);
    Company::factory()->create(['denomination' => 'Unused Co']);
    $bf = BusinessFunction::factory()->create();
    $bf->companies()->sync([$acme->id]);

    $values = $this->postJson('/api/tables/business-functions/values', ['columnId' => 'companies'])->assertOk()->json('data.values');
    expect($values)->toContain('Acme Srl')->not->toContain('Unused Co');
});
