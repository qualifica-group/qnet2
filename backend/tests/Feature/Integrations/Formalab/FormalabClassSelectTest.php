<?php

use App\CustomFields\CustomFieldEntityRegistry;
use App\Models\Attribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Seeders\ClassSelectSeeder;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Support\Trigger;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const FORMALAB_FOR_SELECT_URL = '/api/integrations/formalab/classes/for-select';

// AC-002
it('stores every synced class with its edition id as id', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(2477), formalabClassPayload(12)]);

    app(ClassCatalogSync::class)->handle(Trigger::Manual);

    expect(FormalabClass::query()->orderBy('id')->pluck('edition_id', 'id')->all())->toBe([12 => 12, 2477 => 2477]);
});

it('realigns the ids of an existing catalogue without collisions', function () {
    foreach ([[1, 3], [2, 1], [3, 2], [4, 900]] as [$id, $editionId]) {
        DB::table('formalab_classes')->insert([
            'id' => $id, 'edition_id' => $editionId, 'code' => "C{$editionId}", 'title' => 'T', 'label' => "C{$editionId} - T", 'payload_hash' => str_repeat('a', 64),
        ]);
    }

    (require base_path('integrations/Formalab/database/migrations/2026_11_23_110000_align_formalab_class_ids_with_edition_ids.php'))->up();

    expect(DB::table('formalab_classes')->orderBy('id')->pluck('edition_id', 'id')->all())->toBe([1 => 1, 2 => 2, 3 => 3, 900 => 900]);
});

// AC-001
it('offers the classes to the select: id = edition id, search by code, title or edition, hydrated ids', function () {
    formalabEnable();
    formalabFakeApi([
        formalabClassPayload(10, ['codice' => 'GOL-A', 'start_dt' => '2026-01-10', 'corso' => ['title' => 'Saldatura']]),
        formalabClassPayload(20, ['codice' => 'GOL-B', 'start_dt' => '2026-05-10', 'corso' => ['title' => 'Cucina']]),
        formalabClassPayload(30, ['codice' => 'XYZ', 'start_dt' => '2026-03-10', 'corso' => ['title' => 'Inglese']]),
    ]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);
    Sanctum::actingAs(User::factory()->create());

    $all = $this->getJson(FORMALAB_FOR_SELECT_URL)->assertOk();
    expect(collect($all->json('items'))->pluck('id')->all())->toBe([20, 30, 10])
        ->and($all->json('items.0.label'))->toBe('GOL-B - Cucina')
        ->and($all->json('items.0.subtitle'))->toBe('Sede Nord · Avviata · 10/05/2026 - 30/06/2026');

    expect(collect($this->getJson(FORMALAB_FOR_SELECT_URL.'?search=GOL')->json('items'))->pluck('id')->all())->toBe([20, 10])
        ->and(collect($this->getJson(FORMALAB_FOR_SELECT_URL.'?search=inglese')->json('items'))->pluck('id')->all())->toBe([30])
        ->and(collect($this->getJson(FORMALAB_FOR_SELECT_URL.'?search=10')->json('items'))->pluck('id')->all())->toBe([10]);

    $hydrated = $this->getJson(FORMALAB_FOR_SELECT_URL.'?search=Cucina&ids[]=30')->assertOk();
    expect(collect($hydrated->json('items'))->pluck('id')->all())->toBe([20, 30]);
});

it('requires a login for the select', function () {
    formalabEnable();

    $this->getJson(FORMALAB_FOR_SELECT_URL)->assertUnauthorized();
});

// D-2
it('registers the classes as a relation target only while the integration is on', function () {
    $registry = app(CustomFieldEntityRegistry::class);
    expect($registry->isRelationTarget('formalab-classes'))->toBeFalse();

    formalabEnable();

    expect($registry->isRelationTarget('formalab-classes'))->toBeTrue()
        ->and($registry->modelClassFor('formalab-classes'))->toBe(FormalabClass::class)
        ->and(collect($registry->relationTargets())->firstWhere('entity_type', 'formalab-classes'))
        ->toBe(['entity_type' => 'formalab-classes', 'label' => 'formalab:classes.entity']);
});

// AC-007
it('shows the class table to a super-admin only, read-only', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(5)]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);

    $admin = formalabSuperAdmin();
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/tables/formalab-classes/columns')->assertForbidden();

    Sanctum::actingAs($admin);
    $this->getJson('/api/tables/formalab-classes/columns')->assertOk();
    $rows = $this->postJson('/api/tables/formalab-classes/rows', ['startRow' => 0, 'endRow' => 10])->assertOk();
    expect($rows->json('items.0.edition_id'))->toBe(5)
        ->and($rows->json('items.0.code'))->toBe('COD-5')
        ->and($rows->json('items.0.start_date'))->toBe('2026-03-02')
        ->and($rows->json('items.0.editable'))->toBeFalse();
});

// AC-009
it('converts the course name into the class select and its text values into edition ids, once', function () {
    formalabEnable();
    formalabFakeApi([
        formalabClassPayload(1, ['codice' => 'AAA', 'corso' => ['title' => 'Unique']]),
        formalabClassPayload(2, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(3, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
    ]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);
    $byLabel = formalabGolWorkOrder();
    $byLink = formalabGolWorkOrder();
    $twin = formalabGolWorkOrder();
    $unknown = formalabGolWorkOrder();
    // the text of before 0426, written as the old release stored it
    $golCorso = Attribute::query()->where('code', 'gol_corso')->firstOrFail();
    $golCorso->forceFill(['type' => 'text', 'relation_target' => null])->save();
    foreach ([[$byLabel, 'AAA - Unique'], [$byLink, 'BBB - Twin'], [$twin, 'BBB - Twin'], [$unknown, 'ZZZ - Nobody']] as [$workOrder, $text]) {
        $workOrder->forceFill(['attribute_values' => ['gol_corso' => $text, 'gol_notice' => 'keep']])->saveQuietly();
    }
    FormalabWorkOrderLink::query()->create(['work_order_id' => $byLink->id, 'edition_id' => 3]);

    $this->seed(ClassSelectSeeder::class);

    expect($golCorso->refresh()->type)->toBe('relation')
        ->and($golCorso->relation_target)->toBe(['entity_type' => 'formalab-classes', 'cardinality' => 'one', 'for_select_resource' => 'integrations/formalab/classes'])
        ->and($byLabel->refresh()->attribute_values)->toBe(['gol_corso' => 1, 'gol_notice' => 'keep'])
        ->and($byLink->refresh()->attribute_values['gol_corso'])->toBe(3)
        ->and($twin->refresh()->attribute_values)->toBe(['gol_notice' => 'keep'])
        ->and($unknown->refresh()->attribute_values)->toBe(['gol_notice' => 'keep']);

    $this->seed(ClassSelectSeeder::class);

    expect($byLabel->refresh()->attribute_values['gol_corso'])->toBe(1)->and($byLink->refresh()->attribute_values['gol_corso'])->toBe(3);
});
