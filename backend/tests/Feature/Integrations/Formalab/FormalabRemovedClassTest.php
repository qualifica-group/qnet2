<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Services\EditionRefresher;
use Integrations\Formalab\Services\StatusReport;
use Integrations\Formalab\Support\Trigger;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function formalabRemovedSync(): FormalabSyncRun
{
    return app(ClassCatalogSync::class)->handle(Trigger::Manual);
}

// D-12
it('removes on sync the classes Formalab no longer lists, unlinking their work orders and emptying their class select', function () {
    formalabEnable();
    $catalog = formalabFakeApi([formalabClassPayload(5), formalabClassPayload(6)]);
    formalabRemovedSync();
    $kept = formalabGolWorkOrder(['gol_corso' => 5]);
    $orphan = formalabGolWorkOrder(['gol_corso' => 6, 'gol_notice' => 'keep me']);
    expect(FormalabWorkOrderLink::query()->count())->toBe(2);

    $catalog['classes'] = [formalabClassPayload(5)];
    $run = formalabRemovedSync();

    $values = $orphan->refresh()->attribute_values;
    expect($run->outcome)->toBe('success')->and($run->classes_removed)->toBe(1)
        ->and(FormalabClass::query()->pluck('edition_id')->all())->toBe([5])
        ->and(FormalabWorkOrderLink::query()->pluck('edition_id', 'work_order_id')->all())->toBe([$kept->id => 5])
        ->and($values)->not->toHaveKey('gol_corso')
        ->and($values['gol_stato_corso'])->toBe('In corso')
        ->and($values['gol_notice'])->toBe('keep me')
        ->and($kept->refresh()->attribute_values['gol_corso'])->toBe(5)
        ->and(app(StatusReport::class)->handle()['last_sync']['classes_removed'])->toBe(1);
});

it('removes nothing when Formalab lists no class at all', function () {
    formalabEnable();
    $catalog = formalabFakeApi([formalabClassPayload(5)]);
    formalabRemovedSync();
    formalabGolWorkOrder(['gol_corso' => 5]);

    $catalog['classes'] = [];
    $run = formalabRemovedSync();

    expect($run->classes_removed)->toBe(0)
        ->and(FormalabClass::query()->count())->toBe(1)
        ->and(FormalabWorkOrderLink::query()->count())->toBe(1);
});

it('removes nothing when the page cap stopped the read before the last page', function () {
    formalabEnable(['page_size' => 1, 'max_pages' => 1]);
    $catalog = formalabFakeApi([formalabClassPayload(5)]);
    formalabRemovedSync();
    formalabGolWorkOrder(['gol_corso' => 5]);

    $catalog['classes'] = [formalabClassPayload(7), formalabClassPayload(8)];
    $run = formalabRemovedSync();

    expect($run->classes_removed)->toBe(0)
        ->and(FormalabClass::query()->pluck('edition_id')->sort()->values()->all())->toBe([5, 7])
        ->and(FormalabWorkOrderLink::query()->count())->toBe(1);
});

it('removes a class Formalab answers 404 for on the work order sync', function () {
    formalabEnable();
    $catalog = formalabFakeApi([formalabClassPayload(5)]);
    formalabRemovedSync();
    $workOrder = formalabGolWorkOrder(['gol_corso' => 5]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $catalog['classes'] = [];
    $this->postJson('/api/integrations/formalab/work-orders/'.$workOrder->id.'/sync')
        ->assertUnprocessable()->assertJsonValidationErrors('work_order');

    expect(FormalabClass::query()->count())->toBe(0)
        ->and(FormalabWorkOrderLink::query()->count())->toBe(0)
        ->and($workOrder->refresh()->attribute_values)->not->toHaveKey('gol_corso');
});

it('removes a class Formalab answers 404 for on a webhook refresh', function () {
    formalabEnable();
    $catalog = formalabFakeApi([formalabClassPayload(5), formalabClassPayload(6)]);
    formalabRemovedSync();
    formalabGolWorkOrder(['gol_corso' => 6]);

    $catalog['classes'] = [formalabClassPayload(5)];
    $result = app(EditionRefresher::class)->handle([5, 6], Trigger::Webhook);

    expect($result['refreshed'])->toBe(1)
        ->and(FormalabClass::query()->pluck('edition_id')->all())->toBe([5])
        ->and(FormalabWorkOrderLink::query()->count())->toBe(0);
});
