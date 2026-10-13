<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function approverOptions(object $test, User $requester, ?BusinessFunction $function = null): TestResponse
{
    $query = ['requester_id' => $requester->id] + ($function ? ['business_function_id' => $function->id] : []);

    return $test->getJson('/api/purchase-requests/approver-options?'.http_build_query($query));
}

it('AC-001: lists the active direct superiors of the requester by name', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $second = User::factory()->create(['name' => 'Zeta Boss']);
    $first = User::factory()->create(['name' => 'Alfa Boss']);
    $inactive = User::factory()->inactive()->create(['name' => 'Beta Boss']);
    $requester = User::factory()->reportsTo($second, $first, $inactive)->create();

    approverOptions($this, $requester, BusinessFunction::factory()->withManager()->create())
        ->assertOk()
        ->assertJsonPath('data.source', 'reports_to')
        ->assertJsonPath('data.approvers', [
            ['id' => $first->id, 'name' => 'Alfa Boss'],
            ['id' => $second->id, 'name' => 'Zeta Boss'],
        ]);
});

it('AC-002: falls back to the function manager, or reports none', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['update']));
    $manager = User::factory()->create();
    $requester = User::factory()->create();

    approverOptions($this, $requester, BusinessFunction::factory()->withManager($manager)->create())
        ->assertOk()
        ->assertJsonPath('data.source', 'business_function')
        ->assertJsonPath('data.approvers.0.id', $manager->id);

    approverOptions($this, $manager, BusinessFunction::factory()->withManager($manager)->create())
        ->assertOk()
        ->assertJsonPath('data.source', 'none')
        ->assertJsonPath('data.approvers', []);

    approverOptions($this, $requester, BusinessFunction::factory()->create())
        ->assertJsonPath('data.source', 'none');
});

it('approver-options needs create or update and a valid query', function () {
    $requester = User::factory()->create();

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    approverOptions($this, $requester)->assertForbidden();

    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $this->getJson('/api/purchase-requests/approver-options')->assertUnprocessable()->assertJsonValidationErrors('requester_id');
});

it('AC-003: creating accepts only a direct superior as function manager', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $superior = User::factory()->create();
    $requester = User::factory()->reportsTo($superior)->create();

    foreach ([$requester->id, User::factory()->create()->id] as $wrong) {
        $this->postJson('/api/purchase-requests', purchaseRequestPayload(['requester_id' => $requester->id, 'function_manager_id' => $wrong]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.function_manager_id.0', __('purchase_requests.approver_not_allowed'));
    }

    $this->postJson('/api/purchase-requests', purchaseRequestPayload(['requester_id' => $requester->id, 'function_manager_id' => $superior->id]))
        ->assertCreated();
});

it('AC-004: creating without any possible approver is refused', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $requester = User::factory()->create();

    $this->postJson('/api/purchase-requests', purchaseRequestPayload(['requester_id' => $requester->id]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.function_manager_id.0', __('purchase_requests.no_approver_available'));
});

it('AC-005: a legacy request stays editable until requester, manager or function change', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $request = purchaseRequestWithLines();

    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request, ['subject' => 'Renamed']))->assertOk();

    $this->putJson("/api/purchase-requests/{$request->id}", purchaseRequestUpdatePayload($request, ['requester_id' => User::factory()->create()->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('function_manager_id');
});

it('AC-006: the requester never holds the approve capability on their own request', function () {
    $user = purchaseRequestUserWith(['view']);
    $request = purchaseRequestWithLines([LineStatus::PendingApproval], ['requester_id' => $user->id, 'function_manager_id' => $user->id]);
    Sanctum::actingAs($user);

    changeStatus($this, [$request->lines->first()->id], 'approved')->assertForbidden();

    expect($request->lines->first()->fresh()->status)->toBe(LineStatus::PendingApproval);
});
