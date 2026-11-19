<?php

use App\Models\Attachment;
use App\Models\PurchaseRequestLine;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\RichText\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * An uploaded document shows in the timeline of the record that owns it
 * (config/activity-log.php `documents` relation), only to who can open that
 * record's "Documenti" tab (`documents_permissions`), and never for files
 * outside the `documents` collection (avatar, logo, rich text images).
 */
if (! function_exists('workOrderDocumentsActivityActor')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function workOrderDocumentsActivityActor(WorkOrder $workOrder, array $permissions): User
    {
        $all = ['work-orders.view', 'work-orders.viewActivity', 'work-orders.viewDocuments', 'attachments.viewAny'];

        foreach ($all as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        $workOrder->participants()->attach($user->id, ['position' => 0]);

        return $user;
    }
}

if (! function_exists('activityLogModules')) {
    /**
     * @return array<int, string>
     */
    function activityLogModules(string $resource, int $id): array
    {
        return collect(test()->getJson("/api/activity-log/{$resource}/{$id}")->assertOk()->json('data.items'))
            ->pluck('module')
            ->unique()
            ->values()
            ->all();
    }
}

it('shows a document uploaded on a work order in its timeline', function () {
    $workOrder = WorkOrder::factory()->create();
    $document = Attachment::factory()->for($workOrder, 'attachable')
        ->create(['collection' => Attachment::DOCUMENTS_COLLECTION, 'original_name' => 'certificato.pdf']);
    Sanctum::actingAs(workOrderDocumentsActivityActor(
        $workOrder,
        ['work-orders.view', 'work-orders.viewActivity', 'work-orders.viewDocuments', 'attachments.viewAny'],
    ));

    $entry = collect($this->getJson("/api/activity-log/work-orders/{$workOrder->id}")->assertOk()->json('data.items'))
        ->firstWhere('module', $document->getMorphClass());

    expect($entry)->not->toBeNull()
        ->and($entry['event'])->toBe('created')
        ->and($entry['subject_id'])->toBe($document->id)
        ->and(collect($entry['changes'])->firstWhere('field', 'original_name')['new_value'])->toBe('certificato.pdf');
});

it('hides document entries from who cannot open the Documenti tab', function (array $permissions) {
    $workOrder = WorkOrder::factory()->create();
    Attachment::factory()->for($workOrder, 'attachable')->create(['collection' => Attachment::DOCUMENTS_COLLECTION]);
    Sanctum::actingAs(workOrderDocumentsActivityActor($workOrder, $permissions));

    expect(activityLogModules('work-orders', $workOrder->id))
        ->toContain($workOrder->getMorphClass())
        ->not->toContain((new Attachment)->getMorphClass());
})->with([
    'without work-orders.viewDocuments' => [['work-orders.view', 'work-orders.viewActivity', 'attachments.viewAny']],
    'without attachments.viewAny' => [['work-orders.view', 'work-orders.viewActivity', 'work-orders.viewDocuments']],
]);

it('ignores attachments outside the documents collection', function () {
    $workOrder = WorkOrder::factory()->create();
    Attachment::factory()->for($workOrder, 'attachable')->create(['collection' => RichText::ATTACHMENT_COLLECTION]);
    Sanctum::actingAs(workOrderDocumentsActivityActor(
        $workOrder,
        ['work-orders.view', 'work-orders.viewActivity', 'work-orders.viewDocuments', 'attachments.viewAny'],
    ));

    expect(activityLogModules('work-orders', $workOrder->id))->not->toContain((new Attachment)->getMorphClass());
});

it('shows the documents of a purchase request and of its lines', function () {
    $line = PurchaseRequestLine::factory()->create();
    $requestDocument = Attachment::factory()->for($line->purchaseRequest, 'attachable')
        ->create(['collection' => Attachment::DOCUMENTS_COLLECTION]);
    $lineDocument = Attachment::factory()->for($line, 'attachable')
        ->create(['collection' => Attachment::DOCUMENTS_COLLECTION]);
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Sanctum::actingAs(tap(User::factory()->create())->assignRole('super-admin'));

    $subjectIds = collect($this->getJson("/api/activity-log/purchase-requests/{$line->purchase_request_id}")->assertOk()->json('data.items'))
        ->where('module', (new Attachment)->getMorphClass())
        ->pluck('subject_id')
        ->all();

    expect($subjectIds)->toContain($requestDocument->id)->toContain($lineDocument->id);
});
