<?php

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Support\Trigger;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const FORMALAB_LINK_URL = '/api/integrations/formalab/work-orders/';

const FORMALAB_SYNC_SUFFIX = '/sync';

function formalabCatalog(array $classes): ArrayObject
{
    $catalog = formalabFakeApi($classes);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);

    return $catalog;
}

// AC-007 (REQUIREMENT CHANGED 2026-11-20: the edition is matched by course name, never chosen)
it('syncs by course name: finds the edition, re-reads it live, writes the fields and records the link', function () {
    formalabEnable();
    $catalog = formalabCatalog([formalabClassPayload(5), formalabClassPayload(6)]);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $catalog['classes'] = [formalabClassPayload(5, ['stato_corso' => ['name' => 'Concluso'], 'data_esame' => '2026-07-10']), formalabClassPayload(6)];

    $response = $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertOk();

    $response->assertJsonPath('data.eligible', true)->assertJsonPath('data.can_manage', true)
        ->assertJsonPath('data.link.edition_id', 5)->assertJsonPath('data.link.class.course_status', 'Concluso')
        ->assertJsonPath('data.link.sync_error', null);
    expect($response->json('data.link.linked_by.id'))->not->toBeNull();
    $values = $workOrder->refresh()->attribute_values;
    expect($values['gol_stato_corso'])->toBe('Concluso')->and($values['gol_data_esame'])->toBe('2026-07-10');

    $linkedAt = FormalabWorkOrderLink::query()->value('created_at');
    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertOk();
    expect(FormalabWorkOrderLink::query()->count())->toBe(1)
        ->and(FormalabWorkOrderLink::query()->value('created_at'))->toEqual($linkedAt);

    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertOk()->assertJsonPath('data.link.edition_id', 5);
});

it('follows a changed course name to the edition with that name', function () {
    formalabEnable();
    formalabCatalog([formalabClassPayload(5), formalabClassPayload(6)]);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertOk();

    $workOrder->forceFill(['attribute_values' => [...$workOrder->refresh()->attribute_values, 'gol_corso' => 'COD-6 - Course 6']])->save();

    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertOk()->assertJsonPath('data.link.edition_id', 6);
    expect(FormalabWorkOrderLink::query()->count())->toBe(1);
});

it('refuses the sync without the update permission', function () {
    formalabEnable();
    formalabCatalog([formalabClassPayload(5)]);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5']);
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertForbidden();
    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertOk()->assertJsonPath('data.can_manage', false);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertForbidden();
    expect(FormalabWorkOrderLink::query()->count())->toBe(0);
});

it('answers 404 for an unknown work order', function () {
    formalabEnable();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->getJson(FORMALAB_LINK_URL.'999999')->assertNotFound();
    $this->postJson(FORMALAB_LINK_URL.'999999'.FORMALAB_SYNC_SUFFIX)->assertNotFound();
});

it('refuses a work order without course fields, without a course name, with an unknown or an ambiguous name', function () {
    formalabEnable();
    formalabCatalog([
        formalabClassPayload(5),
        formalabClassPayload(7, ['codice' => 'TWIN', 'corso' => ['title' => 'Same']]),
        formalabClassPayload(8, ['codice' => 'TWIN', 'corso' => ['title' => 'Same']]),
    ]);
    $plain = WorkOrder::factory()->create();
    $cases = [
        $plain,
        formalabGolWorkOrder(),
        formalabGolWorkOrder(['gol_corso' => 'NOPE - Nobody']),
        formalabGolWorkOrder(['gol_corso' => 'TWIN - Same']),
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
    FormalabClass::query()->create(['edition_id' => 5, 'code' => 'COD-5', 'title' => 'Course 5', 'label' => 'COD-5 - Course 5', 'payload_hash' => str_repeat('a', 64)]);
    Http::preventStrayRequests();
    Http::fake(['*/api/login' => Http::response(['accessToken' => 't']), '*/api/classi/*' => Http::response('down', 503)]);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5', 'gol_stato_corso' => 'In corso']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.FORMALAB_SYNC_SUFFIX)->assertStatus(502)->assertJsonPath('success', false);

    expect(FormalabWorkOrderLink::query()->count())->toBe(0)
        ->and($workOrder->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso');
});

// AC-008 — REQUIREMENT CHANGED (spec 0423 D-15): the linking runs inside every catalogue sync, no endpoint of its own
it('links by exact course name, skips ambiguous labels and leaves linked work orders alone', function () {
    formalabEnable();
    formalabFakeApi([
        formalabClassPayload(1, ['codice' => 'AAA', 'corso' => ['title' => 'Unique']]),
        formalabClassPayload(2, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(3, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(4, ['codice' => 'CCC', 'corso' => ['title' => 'Linked']]),
    ]);
    $matching = formalabGolWorkOrder(['gol_corso' => 'AAA - Unique']);
    $ambiguous = formalabGolWorkOrder(['gol_corso' => 'BBB - Twin']);
    $unmatched = formalabGolWorkOrder(['gol_corso' => 'ZZZ - Nobody']);
    $alreadyLinked = formalabGolWorkOrder(['gol_corso' => 'CCC - Linked']);
    $manual = FormalabWorkOrderLink::query()->create(['work_order_id' => $alreadyLinked->id, 'edition_id' => 1]);

    app(ClassCatalogSync::class)->handle(Trigger::Manual);

    expect(FormalabWorkOrderLink::query()->where('work_order_id', $matching->id)->value('edition_id'))->toBe(1)
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $ambiguous->id)->exists())->toBeFalse()
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $unmatched->id)->exists())->toBeFalse()
        ->and($manual->refresh()->edition_id)->toBe(1)
        ->and($matching->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso');
});
