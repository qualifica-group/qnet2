<?php

declare(strict_types=1);

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

// Spec 0256, D-1/D-2: the RDA detail saves one field (or one line) at a time
// through a partial PATCH; an absent key never changes.

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
});

it('AC-001: updates the subject alone and leaves the header and the lines untouched', function () {
    $request = purchaseRequestWithLines([LineStatus::PendingApproval, LineStatus::Approved]);
    $lineIds = $request->lines->pluck('id')->all();

    $this->patchJson("/api/purchase-requests/{$request->id}", ['subject' => 'New subject'])
        ->assertOk()
        ->assertJsonPath('data.subject', 'New subject')
        ->assertJsonPath('data.company.id', $request->company_id)
        ->assertJsonCount(2, 'data.lines');

    expect($request->fresh('lines')->lines->pluck('id')->all())->toBe($lineIds);
});

it('AC-001: a present but blank required field is still refused', function () {
    $request = purchaseRequestWithLines();

    $this->patchJson("/api/purchase-requests/{$request->id}", ['subject' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subject');
});

it('AC-001: a lines-only patch edits the pending line and keeps the header', function () {
    $request = purchaseRequestWithLines();
    $line = $request->lines->first();

    $this->patchJson("/api/purchase-requests/{$request->id}", ['lines' => [[
        'id' => $line->id, 'description' => 'Edited line', 'quantity' => '3', 'unit_price' => '5',
    ]]])
        ->assertOk()
        ->assertJsonPath('data.subject', $request->subject)
        ->assertJsonPath('data.lines.0.description', 'Edited line')
        ->assertJsonPath('data.lines.0.taxable_amount', '15.00');
});

it('AC-002: a new company needs its own site', function () {
    $request = purchaseRequestWithLines();
    $company = Company::factory()->create();
    $otherSite = CompanySite::factory()->create();

    $this->patchJson("/api/purchase-requests/{$request->id}", ['company_id' => $company->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_site_id');

    $this->patchJson("/api/purchase-requests/{$request->id}", ['company_id' => $company->id, 'company_site_id' => $otherSite->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_site_id');

    $site = CompanySite::factory()->create(['company_id' => $company->id]);
    $this->patchJson("/api/purchase-requests/{$request->id}", ['company_id' => $company->id, 'company_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.company_site.id', $site->id);
});

it('AC-002: a site alone must belong to the stored company', function () {
    $request = purchaseRequestWithLines();

    $this->patchJson("/api/purchase-requests/{$request->id}", ['company_site_id' => CompanySite::factory()->create()->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_site_id');

    $sameCompanySite = CompanySite::factory()->create(['company_id' => $request->company_id]);
    $this->patchJson("/api/purchase-requests/{$request->id}", ['company_site_id' => $sameCompanySite->id])->assertOk();
});

it('AC-003: changing only the requester checks the stored approver against the new requester', function () {
    $request = purchaseRequestWithLines();
    $approver = User::factory()->create();

    // The stored approver is no superior of this requester.
    $this->patchJson("/api/purchase-requests/{$request->id}", ['requester_id' => User::factory()->reportsTo($approver)->create()->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('function_manager_id');

    $this->patchJson("/api/purchase-requests/{$request->id}", [
        'requester_id' => User::factory()->reportsTo($approver)->create()->id,
        'function_manager_id' => $approver->id,
    ])->assertOk()->assertJsonPath('data.function_manager.id', $approver->id);
});

it('AC-003: an untouched out-of-rule approver does not block other fields', function () {
    $request = purchaseRequestWithLines();

    $this->patchJson("/api/purchase-requests/{$request->id}", ['notes' => 'Deliver on Monday'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Deliver on Monday');
});

it('keeps the create payload fully required', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));

    $this->postJson('/api/purchase-requests', ['subject' => 'Only a subject'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['requested_at', 'company_id', 'lines']);
});

it('refuses a partial patch on a closed request', function () {
    $request = purchaseRequestWithLines([LineStatus::Received], ['status' => 'closed', 'closed_at' => now()]);

    $this->patchJson("/api/purchase-requests/{$request->id}", ['subject' => 'Late'])->assertStatus(409);
});
