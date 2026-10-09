<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\User;
use Database\Seeders\DemoPurchaseRequestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function restrictCompanyField(object $user): void
{
    $role = Role::create(['name' => 'buyer']);
    $user->assignRole($role);
    RoleFieldPermission::query()->create([
        'role_id' => $role->id, 'resource' => 'purchase-requests', 'field' => 'company_id',
        'visible' => true, 'editable' => false, 'required' => false,
    ]);
}

it('AC-015: a role with company_id read-only cannot change it, and the metadata says so', function () {
    $user = purchaseRequestUserWith(['view', 'viewAll', 'update']);
    restrictCompanyField($user);
    Sanctum::actingAs($user);
    $request = purchaseRequestWithLines();
    $otherCompany = Company::factory()->create();
    $otherSite = CompanySite::factory()->create(['company_id' => $otherCompany->id]);

    $this->getJson("/api/purchase-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.field_permissions.company_id.editable', false)
        ->assertJsonPath('data.field_permissions.company_id.visible', true)
        ->assertJsonPath('data.field_permissions.subject.editable', true);

    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request, [
        'company_id' => $otherCompany->id,
        'company_site_id' => $otherSite->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('company_id');
    expect($request->fresh()->company_id)->not->toBe($otherCompany->id);

    // Resubmitting the stored value is not a change.
    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request, ['subject' => 'Still editable']))
        ->assertOk()
        ->assertJsonPath('data.subject', 'Still editable');
});

it('exposes the abilities and the field permissions of a closed request as read-only', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update', 'close', 'delete']));
    $request = PurchaseRequest::factory()->closed()->create();

    $this->getJson("/api/purchase-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.abilities.update', false)
        ->assertJsonPath('data.abilities.close', false)
        ->assertJsonPath('data.abilities.delete', true)
        ->assertJsonPath('data.field_permissions.subject.editable', false);
});

it('offers the function manager in the business-functions for-select', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view']));
    $manager = purchaseRequestUserWith([]);
    BusinessFunction::factory()->create(['name' => 'Procurement', 'manager_id' => $manager->id]);
    BusinessFunction::factory()->create(['name' => 'Unmanaged']);

    $items = collect($this->getJson('/api/business-functions/for-select')->assertOk()->json('items'))->keyBy('label');

    expect($items['Procurement']['manager'])->toBe(['id' => $manager->id, 'name' => $manager->name])
        // Null optional keys are omitted by the for-select standard.
        ->and($items['Unmanaged'])->not->toHaveKey('manager');
});

it('seeds the demo purchase requests idempotently', function () {
    User::factory()->create();
    CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    BusinessFunction::factory()->create();
    OperationalSite::factory()->create();

    $this->seed(DemoPurchaseRequestSeeder::class);
    $this->seed(DemoPurchaseRequestSeeder::class);

    expect(PurchaseRequest::query()->count())->toBe(2)
        ->and(PurchaseRequest::query()->where('subject', 'like', 'Portatili%')->firstOrFail()->lines()->count())->toBe(4);
});
