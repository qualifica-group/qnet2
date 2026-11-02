<?php

use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Attachments\LegacyRecordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0343 (AC-001..AC-003): the Documents list tells where the documents of
 * a migrated record can still be looked up in the legacy QNet.
 */
uses(RefreshDatabase::class);

const LEGACY_RECORD_BASE = 'https://legacy.test';

beforeEach(function () {
    config(['migrations.legacy_files_base_url' => LEGACY_RECORD_BASE]);
});

it('AC-001 links each migrated owner to its legacy page', function (string $alias, Closure $owner, string $path) {
    expect(app(LegacyRecordLink::class)->forOwner($alias, $owner()->id))->toBe(LEGACY_RECORD_BASE.'/'.$path);
})->with([
    'work order' => ['work_order', fn () => WorkOrder::factory()->create(['old_id' => 501]), 'manageorder/501'],
    'registry' => ['registry', fn () => Registry::factory()->create(['old_id' => 502]), 'companyshow/502'],
    'purchase request' => ['purchase_request', fn () => PurchaseRequest::factory()->create(['old_id' => 503]), 'rda-manage/503'],
    'purchase request line' => [
        'purchase_request_line',
        fn () => PurchaseRequestLine::factory()->create([
            'old_id' => 9999,
            'purchase_request_id' => PurchaseRequest::factory()->create(['old_id' => 504])->id,
        ]),
        'rda-manage/504',
    ],
    'purchase order' => ['purchase_order', fn () => PurchaseOrder::factory()->create(['old_id' => 505]), 'amministrazione.genera-proforma-fornitori/505/view'],
    'purchase invoice' => ['purchase_invoice', fn () => PurchaseInvoice::factory()->create(['old_id' => 506]), 'amministrazione.genera-proforma-fornitori/506/view'],
    'opportunity (list)' => ['opportunity', fn () => Opportunity::factory()->create(['old_id' => 507]), 'opportunities'],
    'task (list)' => ['task', fn () => Task::factory()->create(['old_id' => 508]), 'tasks'],
]);

it('AC-002 gives no link to an owner that was not migrated, has no legacy page, or without the legacy host', function () {
    $service = app(LegacyRecordLink::class);
    $local = WorkOrder::factory()->create(['old_id' => null]);
    $contract = Contract::factory()->create();
    $migrated = WorkOrder::factory()->create(['old_id' => 601]);
    $localLine = PurchaseRequestLine::factory()->create([
        'purchase_request_id' => PurchaseRequest::factory()->create(['old_id' => null])->id,
    ]);

    expect($service->forOwner('work_order', $local->id))->toBeNull()
        ->and($service->forOwner('contract', $contract->id))->toBeNull()
        ->and($service->forOwner('purchase_request_line', $localLine->id))->toBeNull()
        ->and($service->forOwner('work_order', 999999))->toBeNull();

    config(['migrations.legacy_files_base_url' => '']);

    expect($service->forOwner('work_order', $migrated->id))->toBeNull();
});

/**
 * A user who may list the documents of the registries (not an owner-scoped alias).
 */
function legacyRecordLinkActor(): User
{
    Permission::findOrCreate('attachments.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('attachments.viewAny');

    return $actor;
}

it('AC-001 adds meta.legacy_url to the documents list and keeps data unchanged', function () {
    $registry = Registry::factory()->create(['old_id' => 702]);
    Sanctum::actingAs(legacyRecordLinkActor());

    $this->getJson("/api/attachments?attachable_type=registry&attachable_id={$registry->id}")
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.legacy_url', LEGACY_RECORD_BASE.'/companyshow/702');
});

it('AC-002 returns a null meta.legacy_url for a record created in QNet', function () {
    $registry = Registry::factory()->create(['old_id' => null]);
    Sanctum::actingAs(legacyRecordLinkActor());

    $this->getJson("/api/attachments?attachable_type=registry&attachable_id={$registry->id}")
        ->assertOk()
        ->assertJsonPath('meta.legacy_url', null);
});

it('AC-003 refuses the list as today, exposing no legacy link', function () {
    $hidden = WorkOrder::factory()->create(['old_id' => 801]);
    Sanctum::actingAs(legacyRecordLinkActor());

    $this->getJson("/api/attachments?attachable_type=work_order&attachable_id={$hidden->id}")
        ->assertForbidden()
        ->assertJsonMissingPath('meta');
});
