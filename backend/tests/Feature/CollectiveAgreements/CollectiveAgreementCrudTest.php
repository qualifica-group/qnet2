<?php

use App\Models\CollectiveAgreement;
use App\Models\CollectiveAgreementLevel;
use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('0272 AC-001: creating an agreement with two levels answers 201 with the levels in order', function () {
    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['create']));

    $response = $this->postJson('/api/collective-agreements', [
        'name' => 'Commercio',
        'code' => 'COM',
        'levels' => [
            ['code' => 'Q', 'description' => 'Quadri'],
            ['code' => '1', 'description' => null],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Commercio')
        ->assertJsonPath('data.levels.0.code', 'Q')
        ->assertJsonPath('data.levels.0.sort_order', 0)
        ->assertJsonPath('data.levels.1.code', '1')
        ->assertJsonPath('data.levels.1.sort_order', 1)
        ->assertJsonPath('data.levels.1.contracts_count', 0);

    expect(collect($response->json('data.levels'))->pluck('code')->all())->toBe(['Q', '1']);
});

it('0272 AC-001: duplicated name, duplicated level code and a foreign level id are 422', function () {
    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['create', 'update']));
    CollectiveAgreement::factory()->create(['name' => 'Commercio']);
    $foreign = CollectiveAgreementLevel::factory()->create();

    $this->postJson('/api/collective-agreements', ['name' => 'Commercio'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/api/collective-agreements', ['name' => 'Metalmeccanico', 'levels' => [['code' => 'A'], ['code' => 'A']]])
        ->assertStatus(422)->assertJsonValidationErrors('levels.0.code');
    $this->postJson('/api/collective-agreements', ['name' => 'Altro', 'levels' => [['id' => $foreign->id, 'code' => 'A']]])
        ->assertStatus(422)->assertJsonValidationErrors('levels.0.id');

    $own = CollectiveAgreement::factory()->create();
    $this->putJson("/api/collective-agreements/{$own->id}", ['levels' => [['id' => $foreign->id, 'code' => 'A']]])
        ->assertStatus(422)->assertJsonValidationErrors('levels.0.id');
});

it('0272 AC-001: update replaces the levels (update by id, create, delete absent, swap codes, order)', function () {
    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['update']));
    $agreement = CollectiveAgreement::factory()->create();
    $a = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'A', 'sort_order' => 0]);
    $b = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'B', 'sort_order' => 1]);
    $gone = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'C', 'sort_order' => 2]);

    $response = $this->putJson("/api/collective-agreements/{$agreement->id}", [
        'levels' => [
            ['id' => $b->id, 'code' => 'A'],
            ['id' => $a->id, 'code' => 'B', 'description' => 'swapped'],
            ['code' => 'D'],
        ],
    ])->assertOk();

    expect(collect($response->json('data.levels'))->map(fn ($l) => [$l['id'], $l['code']])->all())
        ->toBe([[$b->id, 'A'], [$a->id, 'B'], [$response->json('data.levels.2.id'), 'D']])
        ->and(CollectiveAgreementLevel::find($gone->id))->toBeNull();

    $this->putJson("/api/collective-agreements/{$agreement->id}", ['name' => 'Renamed'])
        ->assertOk()->assertJsonCount(3, 'data.levels');
});

it('0272 AC-002: removing a used level is 422 on levels and leaves everything untouched', function () {
    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['update']));
    $agreement = CollectiveAgreement::factory()->create();
    $used = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create();
    $free = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create();
    EmploymentContract::factory()->create(['collective_agreement_level_id' => $used->id]);

    $this->putJson("/api/collective-agreements/{$agreement->id}", ['name' => 'X', 'levels' => [['id' => $free->id, 'code' => 'Z']]])
        ->assertStatus(422)->assertJsonValidationErrors('levels');

    expect(CollectiveAgreementLevel::find($used->id))->not->toBeNull()
        ->and($agreement->fresh()->name)->not->toBe('X')
        ->and($free->fresh()->code)->not->toBe('Z');

    $this->putJson("/api/collective-agreements/{$agreement->id}", ['levels' => [['id' => $used->id, 'code' => 'U']]])
        ->assertOk()->assertJsonPath('data.levels.0.contracts_count', 1);
});

it('0272 AC-002: deleting an agreement with a used level is 409, a free one is 204', function () {
    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['delete', 'view']));
    $used = CollectiveAgreement::factory()->create();
    $level = CollectiveAgreementLevel::factory()->for($used, 'agreement')->create();
    EmploymentContract::factory()->create(['collective_agreement_level_id' => $level->id]);
    $free = CollectiveAgreement::factory()->create();
    CollectiveAgreementLevel::factory()->for($free, 'agreement')->create();

    $this->deleteJson("/api/collective-agreements/{$used->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('collective_agreements.in_use'));
    $this->deleteJson("/api/collective-agreements/{$free->id}")->assertNoContent();

    expect(CollectiveAgreement::find($free->id))->toBeNull()
        ->and(CollectiveAgreementLevel::where('collective_agreement_id', $free->id)->exists())->toBeFalse();
});

it('0272 AC-003: every CRUD and table endpoint answers 403 without permissions', function () {
    Sanctum::actingAs(User::factory()->create());
    $id = CollectiveAgreement::factory()->create()->id;

    $this->getJson("/api/collective-agreements/{$id}")->assertForbidden();
    $this->postJson('/api/collective-agreements', ['name' => 'X'])->assertForbidden();
    $this->putJson("/api/collective-agreements/{$id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/collective-agreements/{$id}")->assertForbidden();
    $this->getJson('/api/tables/collective-agreements/columns')->assertForbidden();
    $this->postJson('/api/tables/collective-agreements/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0272 AC-003: each ability opens only its own endpoint', function () {
    $id = CollectiveAgreement::factory()->create()->id;

    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['view']));
    $this->getJson("/api/collective-agreements/{$id}")->assertOk()
        ->assertJsonPath('data.abilities', ['update' => false, 'delete' => false]);
    $this->postJson('/api/collective-agreements', ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/collective-agreements/{$id}")->assertForbidden();

    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['create']));
    $this->postJson('/api/collective-agreements', ['name' => 'Brand new'])->assertCreated();
    $this->getJson("/api/collective-agreements/{$id}")->assertForbidden();
});

it('0272 AC-003: the level for-select needs only a login and labels "CCNL – code"', function () {
    $this->getJson('/api/collective-agreement-levels/for-select')->assertUnauthorized();

    $agreement = CollectiveAgreement::factory()->create(['name' => 'Commercio']);
    $level = CollectiveAgreementLevel::factory()->for($agreement, 'agreement')->create(['code' => 'Q']);
    CollectiveAgreementLevel::factory()->for(CollectiveAgreement::factory()->create(['name' => 'Edilizia']), 'agreement')->create(['code' => '7']);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/collective-agreement-levels/for-select')->assertOk()
        ->assertJsonPath('items.0', ['id' => $level->id, 'label' => 'Commercio – Q'])
        ->assertJsonPath('items.1.label', 'Edilizia – 7');
    $this->getJson('/api/collective-agreement-levels/for-select?search=edil')->assertOk()->assertJsonCount(1, 'items');
    $this->getJson("/api/collective-agreement-levels/for-select?search=edil&ids[]={$level->id}")->assertOk()
        ->assertJsonCount(2, 'items')->assertJsonPath('pagination.total', 1);
});

it('0272 AC-003: the grid exposes name, code and levels_count', function () {
    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['viewAny', 'view']));
    $agreement = CollectiveAgreement::factory()->create(['code' => 'COM']);
    CollectiveAgreementLevel::factory()->count(2)->for($agreement, 'agreement')->create();

    $columns = collect($this->getJson('/api/tables/collective-agreements/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/collective-agreements/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($columns)->toBe(['id', 'name', 'code', 'levels_count'])
        ->and($row['code'])->toBe('COM')
        ->and($row['levels_count'])->toBe(2);
});

it('0272 AC-003: the field catalogue describes the levels field', function () {
    Sanctum::actingAs(hrCatalogUserWith('collective-agreements', ['view']));

    $fields = collect($this->getJson('/api/meta/collective-agreements')->assertOk()->json('data.fields'))->pluck('key')->all();

    expect($fields)->toBe(['name', 'code', 'levels']);
});
