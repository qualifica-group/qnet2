<?php

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const FORMALAB_LINK_URL = '/api/integrations/formalab/work-orders/';

const FORMALAB_SYNC_SUFFIX = '/sync';

function formalabCatalog(array $classes): ArrayObject
{
    $catalog = formalabFakeApi($classes);
    formalabSync();

    return $catalog;
}

// AC-006 (REQUIREMENT CHANGED spec 0426: the edition is the one chosen in the class select, by edition id)
it('syncs the edition chosen in the class select: re-reads it live, writes the fields and keeps one link', function () {
    formalabEnable();
    $catalog = formalabCatalog([formalabClassPayload(5), formalabClassPayload(6)]);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 5]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $catalog['classes'] = [formalabClassPayload(5, ['stato_corso' => ['name' => 'Concluso'], 'data_esame' => '2026-07-10']), formalabClassPayload(6)];

    $response = $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertOk();

    $response->assertJsonPath('data.eligible', true)->assertJsonPath('data.can_manage', true)
        ->assertJsonPath('data.link.edition_id', 5)->assertJsonPath('data.link.class.course_status', 'Concluso')
        ->assertJsonPath('data.link.sync_error', null);
    $values = $workOrder->refresh()->attribute_values;
    expect($values['gol_stato_corso'])->toBe('Concluso')->and($values['gol_data_esame'])->toBe('2026-07-10')
        ->and($values['gol_corso'])->toBe(5);

    $linkedAt = FormalabWorkOrderLink::query()->value('created_at');
    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertOk();
    expect(FormalabWorkOrderLink::query()->count())->toBe(1)
        ->and(FormalabWorkOrderLink::query()->value('created_at'))->toEqual($linkedAt);

    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertOk()->assertJsonPath('data.link.edition_id', 5);
});

// AC-004
it('follows the class select on save: links at once, moves to another edition, unlinks when cleared', function () {
    formalabEnable();
    formalabCatalog([formalabClassPayload(5), formalabClassPayload(6, ['stato_corso' => ['name' => 'Concluso']])]);
    $actor = workOrderUserWith(['view', 'update']);
    $this->actingAs($actor);

    $workOrder = formalabGolWorkOrder(['gol_corso' => 5]);
    $link = FormalabWorkOrderLink::query()->where('work_order_id', $workOrder->id)->firstOrFail();
    expect($link->edition_id)->toBe(5)->and($link->linked_by)->toBe($actor->id)->and($link->synced_at)->not->toBeNull()
        ->and($workOrder->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso');

    $workOrder->forceFill(['attribute_values' => [...$workOrder->attribute_values, 'gol_corso' => 6]])->save();
    expect(FormalabWorkOrderLink::query()->sole()->edition_id)->toBe(6)
        ->and($workOrder->refresh()->attribute_values['gol_stato_corso'])->toBe('Concluso');

    $workOrder->forceFill(['attribute_values' => [...$workOrder->attribute_values, 'gol_corso' => null]])->save();
    expect(FormalabWorkOrderLink::query()->count())->toBe(0)
        ->and($workOrder->refresh()->attribute_values['gol_stato_corso'])->toBe('Concluso');
});

it('does not link an edition the catalogue does not have, nor a work order of an unlinked category', function () {
    formalabEnable();
    formalabCatalog([formalabClassPayload(5)]);

    formalabGolWorkOrder(['gol_corso' => 99]);
    formalabGolWorkOrder(['gol_corso' => 5], linkCategory: false);

    expect(FormalabWorkOrderLink::query()->count())->toBe(0);
});

it('refuses the sync without the update permission', function () {
    formalabEnable();
    formalabCatalog([formalabClassPayload(5)]);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 5]);
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertForbidden();
    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertOk()->assertJsonPath('data.can_manage', false);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertForbidden();
});

it('answers 404 for an unknown work order', function () {
    formalabEnable();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->getJson(FORMALAB_LINK_URL.'999999')->assertNotFound();
    $this->postJson(FORMALAB_LINK_URL.'999999'.FORMALAB_SYNC_SUFFIX)->assertNotFound();
});

// REQUIREMENT CHANGED (spec 0426): no name matching, so no "unknown" or "ambiguous" name; an edition Formalab no longer has is refused
it('refuses a work order without course fields, without a class, or with an edition Formalab does not have', function () {
    formalabEnable();
    formalabCatalog([formalabClassPayload(5)]);
    $plain = WorkOrder::factory()->create();
    $cases = [
        $plain,
        formalabGolWorkOrder(),
        formalabGolWorkOrder(['gol_corso' => 99]),
    ];
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    foreach ($cases as $workOrder) {
        $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertUnprocessable()->assertJsonValidationErrors('work_order');
    }

    $this->getJson(FORMALAB_LINK_URL.$plain->id)->assertOk()->assertJsonPath('data.eligible', false);
    expect(FormalabWorkOrderLink::query()->count())->toBe(0);
});

it('answers 502 and saves nothing when Formalab is not reachable', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake(['*/api/login' => Http::response(['accessToken' => 't']), '*/api/classi/*' => Http::response('down', 503)]);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 5, 'gol_stato_corso' => 'In corso']);
    FormalabClass::query()->create(['id' => 5, 'edition_id' => 5, 'code' => 'COD-5', 'title' => 'Course 5', 'label' => 'COD-5 - Course 5', 'payload_hash' => str_repeat('a', 64)]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertStatus(502)->assertJsonPath('success', false);

    expect(FormalabWorkOrderLink::query()->count())->toBe(0)
        ->and($workOrder->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso');
});

// AC-005 — REQUIREMENT CHANGED (spec 0426): the catalogue sync links by edition id only; a label shared by two classes no longer matters
it('links on sync by edition id, the twin labels included, and leaves linked work orders alone', function () {
    formalabEnable();
    formalabFakeApi([
        formalabClassPayload(1, ['codice' => 'AAA', 'corso' => ['title' => 'Unique']]),
        formalabClassPayload(2, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(3, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(4, ['codice' => 'CCC', 'corso' => ['title' => 'Linked']]),
    ]);
    $matching = formalabGolWorkOrder(['gol_corso' => 1]);
    $twin = formalabGolWorkOrder(['gol_corso' => 3]);
    $unmatched = formalabGolWorkOrder(['gol_corso' => 99]);
    $alreadyLinked = formalabGolWorkOrder(['gol_corso' => 4]);
    $manual = FormalabWorkOrderLink::query()->create(['work_order_id' => $alreadyLinked->id, 'edition_id' => 4]);

    formalabSync();

    expect(FormalabWorkOrderLink::query()->where('work_order_id', $matching->id)->value('edition_id'))->toBe(1)
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $twin->id)->value('edition_id'))->toBe(3)
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $unmatched->id)->exists())->toBeFalse()
        ->and($manual->refresh()->edition_id)->toBe(4)
        ->and($matching->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso');
});
