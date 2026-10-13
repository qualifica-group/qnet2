<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

// Spec 0247 D-2 / AC-012: a reason is mandatory for rejected and on_hold.

it('AC-012: rejecting without a reason is a 422 on reason, with one it is 200', function () {
    $manager = purchaseRequestUserWith(['view']);
    $request = purchaseRequestWithLines([LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    $lineId = $request->lines->first()->id;
    Sanctum::actingAs($manager);

    changeStatus($this, [$lineId], 'rejected')
        ->assertUnprocessable()
        ->assertJsonPath('errors.reason', [__('purchase_request_lines.reason_required')]);
    changeStatus($this, [$lineId], 'rejected', ['reason' => '   '])->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect($request->lines()->first()->status)->toBe(LineStatus::PendingApproval);

    changeStatus($this, [$lineId], 'rejected', ['reason' => 'Out of budget'])->assertOk();
    expect($request->lines()->first()->status)->toBe(LineStatus::Rejected);
});

it('AC-012: putting lines on hold, also in bulk, needs a reason', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'fulfill']));
    $request = purchaseRequestWithLines([LineStatus::Approved, LineStatus::Approved]);
    $ids = $request->lines->pluck('id')->all();

    changeStatus($this, $ids, 'on_hold')->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect($request->lines()->where('status', LineStatus::OnHold->value)->count())->toBe(0);

    changeStatus($this, $ids, 'on_hold', ['reason' => 'Waiting for the supplier'])->assertOk();
    expect($request->lines()->where('status', LineStatus::OnHold->value)->count())->toBe(2);
});

it('AC-012: the other target statuses keep the reason optional', function () {
    $manager = purchaseRequestUserWith(['view']);
    $request = purchaseRequestWithLines([LineStatus::PendingApproval], ['function_manager_id' => $manager->id]);
    Sanctum::actingAs($manager);

    changeStatus($this, [$request->lines->first()->id], 'approved')->assertOk();
});
