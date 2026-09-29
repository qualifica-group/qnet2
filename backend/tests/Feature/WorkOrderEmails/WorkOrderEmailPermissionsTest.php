<?php

use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\WorkOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Permissions — spec 0175, AC-016
|--------------------------------------------------------------------------
|
| permissions:sync registers the standard CRUD set for the two new
| configurators plus the two work-orders extras; WorkOrderResource exposes
| both extras under permissions.actions.
*/

uses(RefreshDatabase::class);

it('AC-016: permissions:sync creates the 8 standard permissions for email-templates and document-bundles', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['email-templates', 'document-bundles'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            expect(Permission::query()->where('name', "{$resource}.{$ability}")->exists())
                ->toBeTrue("missing permission {$resource}.{$ability}");
        }
    }
});

it('AC-016: permissions:sync creates work-orders.viewEmails and work-orders.sendEmail', function () {
    expect(WorkOrderPolicy::abilities())->toContain('viewEmails')->toContain('sendEmail');

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'work-orders.viewEmails')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'work-orders.sendEmail')->exists())->toBeTrue();
});

it('AC-016: WorkOrderResource exposes permissions.actions.view_emails and send_email, mirroring the abilities', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'viewDocuments', 'viewEmails', 'sendEmail'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $withBoth = User::factory()->create();
    $withBoth->givePermissionTo(['work-orders.view', 'work-orders.viewEmails', 'work-orders.sendEmail']);
    $withNeither = User::factory()->create();
    $withNeither->givePermissionTo(['work-orders.view']);

    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach([$withBoth->id, $withNeither->id]);

    Sanctum::actingAs($withBoth);
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_emails', true)
        ->assertJsonPath('permissions.actions.send_email', true);

    Sanctum::actingAs($withNeither);
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_emails', false)
        ->assertJsonPath('permissions.actions.send_email', false);
});
