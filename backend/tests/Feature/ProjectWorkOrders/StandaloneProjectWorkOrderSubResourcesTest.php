<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Spec 0431: every tab of the detail answers on a work order born without an
 * offer — none of them may assume an offer behind it (regression of
 * 2026-11-25: contract-data and the proforma summary answered 500).
 */
it('answers every detail sub-resource without a server error', function (string $path) {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin', 'web'));
    Sanctum::actingAs($admin);
    $workOrder = standaloneProjectWorkOrder();

    $status = $this->getJson('/api/work-orders/'.$workOrder->id.$path)->status();

    expect($status)->toBe(200);
})->with([
    '', '/chat/messages', '/contract-data', '/costs', '/document-requests', '/document-requests/defaults',
    '/emails', '/emails/compose-context', '/invoices', '/page', '/proforma-requests/summary',
    '/progress-entries', '/stages', '/task-board',
]);
