<?php

use App\Models\Attachment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\WorkOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Documents on a Commessa (spec 0134, D-4)
|--------------------------------------------------------------------------
|
| The WorkOrder registers itself as an owner of the polymorphic Attachment
| subsystem; no endpoint is added. Documents behave exactly like those of an
| Opportunita'/Task: `work-orders.viewDocuments` only opens the tab, each
| attachment endpoint stays authorized by `attachments.*`.
*/

if (! function_exists('workOrderDocumentActor')) {
    /**
     * @param  array<int, string>  $workOrderAbilities
     * @param  array<int, string>  $attachmentAbilities
     */
    function workOrderDocumentActor(array $workOrderAbilities, array $attachmentAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("attachments.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($workOrderAbilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        foreach ($attachmentAbilities as $ability) {
            $user->givePermissionTo("attachments.{$ability}");
        }

        return $user;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

it('AC-009: `work_order` is an attachable alias matching the morph alias already in the map', function () {
    expect(config('attachments.attachable_types.work_order'))->toBe(WorkOrder::class)
        ->and(WorkOrder::factory()->create()->getMorphClass())->toBe('work_order');
});

it('AC-009: a file is uploaded against attachable_type=work_order and listed back', function () {
    $actor = workOrderDocumentActor(['view'], ['create', 'viewAny']);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'work_order',
        'attachable_id' => $workOrder->id,
        'collection' => 'documents',
        'file' => UploadedFile::fake()->create('verbale.pdf', 32, 'application/pdf'),
    ])->assertCreated()->assertJsonPath('data.attachable_type', 'work_order');

    $this->getJson("/api/attachments?attachable_type=work_order&attachable_id={$workOrder->id}&collection=documents")
        ->assertOk()
        ->assertJsonPath('data.0.original_name', 'verbale.pdf');
});

it('AC-009: a work order document upload is 403 without attachments.create', function () {
    $actor = workOrderDocumentActor(['view'], ['viewAny']);
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'attachable_type' => 'work_order',
        'attachable_id' => $workOrder->id,
        'file' => UploadedFile::fake()->create('vietato.pdf', 8, 'application/pdf'),
    ])->assertForbidden();

    expect(Attachment::count())->toBe(0);
});

it('AC-010: viewDocuments is a synced ability and permissions.actions.view_documents mirrors it', function () {
    expect(WorkOrderPolicy::abilities())->toContain('viewDocuments');

    $withPermission = workOrderDocumentActor(['view', 'viewDocuments']);
    $withoutPermission = workOrderDocumentActor(['view']);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach([$withPermission->id, $withoutPermission->id]);

    Sanctum::actingAs($withPermission);
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_documents', true);

    Sanctum::actingAs($withoutPermission);
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_documents', false);
});

it('AC-011: deleting a work order removes its attachment rows and their binaries', function () {
    $workOrder = WorkOrder::factory()->create();
    $attachment = $workOrder->attach(UploadedFile::fake()->create('da-cancellare.pdf', 8, 'application/pdf'));
    Storage::disk('local')->assertExists($attachment->path);

    $workOrder->delete();

    expect(Attachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});
