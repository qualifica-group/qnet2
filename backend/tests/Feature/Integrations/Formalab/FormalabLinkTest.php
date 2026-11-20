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

// AC-007
it('links a work order to an edition with a live read and writes the course fields', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(5)]);
    $workOrder = formalabGolWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $response = $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 5])->assertOk();

    $response->assertJsonPath('data.eligible', true)->assertJsonPath('data.can_manage', true)
        ->assertJsonPath('data.link.edition_id', 5)->assertJsonPath('data.link.class.label', 'COD-5 - Course 5')
        ->assertJsonPath('data.link.sync_error', null);
    expect($response->json('data.link.linked_by.id'))->not->toBeNull()
        ->and($workOrder->refresh()->attribute_values['gol_corso'])->toBe('COD-5 - Course 5');

    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertOk()->assertJsonPath('data.link.edition_id', 5);
});

it('replaces and removes a link leaving the fields as they were', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(5), formalabClassPayload(6)]);
    $workOrder = formalabGolWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 5])->assertOk();
    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 6])->assertOk()->assertJsonPath('data.link.edition_id', 6);
    expect(FormalabWorkOrderLink::query()->count())->toBe(1);

    $this->deleteJson(FORMALAB_LINK_URL.$workOrder->id)->assertOk()->assertJsonPath('data.link', null);
    expect(FormalabWorkOrderLink::query()->count())->toBe(0)
        ->and($workOrder->refresh()->attribute_values['gol_corso'])->toBe('COD-6 - Course 6');
});

it('refuses a link without the update permission', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(5)]);
    $workOrder = formalabGolWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 5])->assertForbidden();
    $this->deleteJson(FORMALAB_LINK_URL.$workOrder->id)->assertForbidden();
    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertOk()->assertJsonPath('data.can_manage', false);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(FORMALAB_LINK_URL.$workOrder->id)->assertForbidden();
    expect(FormalabWorkOrderLink::query()->count())->toBe(0);
});

it('answers 404 for an unknown work order', function () {
    formalabEnable();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->getJson(FORMALAB_LINK_URL.'999999')->assertNotFound();
});

it('rejects a missing edition, a work order without the course fields and an unknown class', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(5)]);
    $workOrder = formalabGolWorkOrder();
    $plain = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, [])->assertUnprocessable()->assertJsonValidationErrors('edition_id');
    $this->putJson(FORMALAB_LINK_URL.$plain->id, ['edition_id' => 5])->assertUnprocessable()->assertJsonValidationErrors('work_order');
    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 404])->assertUnprocessable()->assertJsonValidationErrors('edition_id');

    $this->getJson(FORMALAB_LINK_URL.$plain->id)->assertOk()->assertJsonPath('data.eligible', false);
    expect(FormalabWorkOrderLink::query()->count())->toBe(0);
});

it('answers 502 and saves nothing when Formalab is not reachable', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake(['*/api/login' => Http::response(['accessToken' => 't']), '*/api/classi/*' => Http::response('down', 503)]);
    $workOrder = formalabGolWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 5])->assertStatus(502)->assertJsonPath('success', false);

    expect(FormalabWorkOrderLink::query()->count())->toBe(0);
});

it('syncs the linked edition on demand, keeping the link, and refuses what it cannot do', function () {
    formalabEnable();
    $catalog = formalabFakeApi([formalabClassPayload(5)]);
    $workOrder = formalabGolWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    // not linked yet: nothing to re-read
    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.'/sync')->assertUnprocessable();

    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 5])->assertOk();
    $linkedAt = FormalabWorkOrderLink::query()->value('created_at');
    $catalog['classes'] = [formalabClassPayload(5, ['stato_corso' => ['name' => 'Concluso'], 'data_esame' => '2026-07-10'])];

    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.'/sync')->assertOk()
        ->assertJsonPath('data.link.edition_id', 5)->assertJsonPath('data.link.class.course_status', 'Concluso');

    $values = $workOrder->refresh()->attribute_values;
    expect($values['gol_stato_corso'])->toBe('Concluso')->and($values['gol_data_esame'])->toBe('2026-07-10')
        ->and(FormalabWorkOrderLink::query()->count())->toBe(1)
        ->and(FormalabWorkOrderLink::query()->value('created_at'))->toEqual($linkedAt);

    Sanctum::actingAs(workOrderUserWith(['view']));
    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.'/sync')->assertForbidden();
});

it('answers 502 on an on-demand sync when Formalab is not reachable and leaves the fields alone', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake([
        '*/api/login' => Http::response(['accessToken' => 't']),
        '*/api/classi/*' => Http::sequence()->push(['classe' => formalabClassPayload(5)])->push('down', 503),
    ]);
    $workOrder = formalabGolWorkOrder();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $this->putJson(FORMALAB_LINK_URL.$workOrder->id, ['edition_id' => 5])->assertOk();

    $this->postJson(FORMALAB_LINK_URL.$workOrder->id.'/sync')->assertStatus(502);

    expect($workOrder->refresh()->attribute_values['gol_corso'])->toBe('COD-5 - Course 5');
});

// AC-008
it('links by exact course name, skips ambiguous labels and leaves linked work orders alone', function () {
    formalabEnable();
    formalabFakeApi([
        formalabClassPayload(1, ['codice' => 'AAA', 'corso' => ['title' => 'Unique']]),
        formalabClassPayload(2, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(3, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(4, ['codice' => 'CCC', 'corso' => ['title' => 'Linked']]),
    ]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);
    $matching = formalabGolWorkOrder(['gol_corso' => 'AAA - Unique']);
    $ambiguous = formalabGolWorkOrder(['gol_corso' => 'BBB - Twin']);
    $unmatched = formalabGolWorkOrder(['gol_corso' => 'ZZZ - Nobody']);
    $alreadyLinked = formalabGolWorkOrder(['gol_corso' => 'CCC - Linked']);
    $manual = FormalabWorkOrderLink::query()->create(['work_order_id' => $alreadyLinked->id, 'edition_id' => 1]);
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/link-work-orders')->assertOk()
        ->assertJsonPath('data', ['linked' => 1, 'ambiguous' => 1, 'unmatched' => 1]);

    expect(FormalabWorkOrderLink::query()->where('work_order_id', $matching->id)->value('edition_id'))->toBe(1)
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $ambiguous->id)->exists())->toBeFalse()
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $unmatched->id)->exists())->toBeFalse()
        ->and($manual->refresh()->edition_id)->toBe(1)
        ->and($matching->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso');
});

it('searches the local catalogue for users who can update work orders', function () {
    formalabEnable();
    foreach ([[1, 'Alpha', 'Torino', '2026-01-01'], [2, 'Beta', 'Roma', '2026-05-01'], [3, 'Gamma', 'Roma', '2026-03-01']] as [$id, $title, $city, $start]) {
        FormalabClass::query()->create([
            'edition_id' => $id, 'code' => "K{$id}", 'title' => $title, 'label' => "K{$id} - {$title}",
            'site_city' => $city, 'start_date' => $start, 'payload_hash' => str_repeat('b', 64),
        ]);
    }

    Sanctum::actingAs(workOrderUserWith(['update']));
    $this->getJson('/api/integrations/formalab/classes')->assertOk()->assertJsonPath('data.0.edition_id', 2);
    $this->getJson('/api/integrations/formalab/classes?search=roma&limit=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/integrations/formalab/classes?search=Alp')->assertOk()->assertJsonPath('data.0.edition_id', 1);
    $this->getJson('/api/integrations/formalab/classes?limit=51')->assertUnprocessable();

    Sanctum::actingAs(workOrderUserWith(['view']));
    $this->getJson('/api/integrations/formalab/classes')->assertForbidden();
});
