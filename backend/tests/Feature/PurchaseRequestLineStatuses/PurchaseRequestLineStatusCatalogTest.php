<?php

use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\PurchaseRequestLineStatusLabel;
use App\Models\User;
use App\Policies\PurchaseRequestLineStatusLabelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// spec 0248: presentation catalog of the RDA line statuses (AC-002, AC-004, AC-006).

function lineStatusActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'update', 'export', 'viewActivity'] as $ability) {
        Permission::findOrCreate("purchase-request-line-statuses.{$ability}", 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("purchase-request-line-statuses.{$ability}");
    }

    return $user;
}

function lineStatusLabel(LineStatus $status): PurchaseRequestLineStatusLabel
{
    return PurchaseRequestLineStatusLabel::query()->where('system_key', $status->value)->firstOrFail();
}

it('AC-002: show returns the contract shape with the permissions block', function () {
    Sanctum::actingAs(lineStatusActorWith(['view']));
    $label = lineStatusLabel(LineStatus::OnHold);

    $this->getJson("/api/purchase-request-line-statuses/{$label->id}")
        ->assertOk()
        ->assertJsonPath('data.system_key', 'on_hold')
        ->assertJsonPath('data.name', 'Stand by')
        ->assertJsonStructure(['permissions', 'data' => ['id', 'system_key', 'name', 'color', 'icon', 'sort_order']]);
});

it('AC-002: updates name, color and icon with the update permission', function () {
    Sanctum::actingAs(lineStatusActorWith(['update']));
    $label = lineStatusLabel(LineStatus::Approved);

    $this->patchJson("/api/purchase-request-line-statuses/{$label->id}", ['name' => 'Autorizzato', 'color' => 'green', 'icon' => null])
        ->assertOk()
        ->assertJsonPath('data.name', 'Autorizzato')
        ->assertJsonPath('data.color', 'green')
        ->assertJsonPath('data.icon', null)
        ->assertJsonPath('data.system_key', 'approved');

    expect($label->fresh()->name)->toBe('Autorizzato');
});

it('AC-002: the endpoints are forbidden without the matching permission', function () {
    $label = lineStatusLabel(LineStatus::Approved);
    Sanctum::actingAs(lineStatusActorWith(['view', 'viewAny']));

    $this->patchJson("/api/purchase-request-line-statuses/{$label->id}", ['name' => 'X'])->assertForbidden();
    $this->postJson('/api/purchase-request-line-statuses/reorder', ['ordered_ids' => [$label->id]])->assertForbidden();

    Sanctum::actingAs(lineStatusActorWith([]));
    $this->getJson("/api/purchase-request-line-statuses/{$label->id}")->assertForbidden();
});

it('AC-002: validates the payload and refuses system_key and sort_order', function (array $payload, string $key) {
    Sanctum::actingAs(lineStatusActorWith(['update']));
    $label = lineStatusLabel(LineStatus::Approved);

    $this->patchJson("/api/purchase-request-line-statuses/{$label->id}", $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
})->with([
    'duplicate name' => [['name' => 'Ordinato'], 'name'],
    'empty name' => [['name' => ''], 'name'],
    'unknown color' => [['color' => '#ff0000'], 'color'],
    'unknown icon' => [['icon' => 'nope'], 'icon'],
    'system_key' => [['system_key' => 'other'], 'system_key'],
    'sort_order' => [['sort_order' => 3], 'sort_order'],
]);

it('AC-002: there is no create or delete route and the policy has no such abilities', function () {
    $label = lineStatusLabel(LineStatus::Approved);
    Sanctum::actingAs(lineStatusActorWith(['view', 'update']));

    expect($this->postJson('/api/purchase-request-line-statuses', ['name' => 'X', 'color' => 'blue'])->status())->toBeIn([404, 405])
        ->and($this->deleteJson("/api/purchase-request-line-statuses/{$label->id}")->status())->toBeIn([404, 405])
        ->and(PurchaseRequestLineStatusLabel::query()->count())->toBe(6);
    expect(PurchaseRequestLineStatusLabelPolicy::abilities())->toBe(['viewAny', 'view', 'update', 'export', 'viewActivity']);
});

it('AC-002: system_key is not mass-assignable', function () {
    $label = lineStatusLabel(LineStatus::Approved);

    $label->fill(['system_key' => 'other', 'name' => 'Renamed'])->save();

    expect($label->fresh()->system_key)->toBe('approved')->and($label->fresh()->name)->toBe('Renamed');
});

it('AC-004: reorder resequences every row and is reflected in for-select', function () {
    Sanctum::actingAs(lineStatusActorWith(['update']));
    $ids = PurchaseRequestLineStatusLabel::query()->orderBy('sort_order')->pluck('id')->reverse()->values()->all();

    $response = $this->postJson('/api/purchase-request-line-statuses/reorder', ['ordered_ids' => $ids])->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe($ids);

    $items = collect($this->getJson('/api/purchase-request-line-statuses/for-select')->assertOk()->json('items'));
    expect($items->pluck('id')->all())->toBe($ids)
        ->and($items->first()['meta'])->toHaveKeys(['system_key', 'color', 'icon', 'sort_order']);
});

it('AC-004: reorder with an incomplete, duplicated or foreign set is rejected', function (callable $ids) {
    Sanctum::actingAs(lineStatusActorWith(['update']));
    $all = PurchaseRequestLineStatusLabel::query()->orderBy('sort_order')->pluck('id')->all();

    $this->postJson('/api/purchase-request-line-statuses/reorder', ['ordered_ids' => $ids($all)])->assertUnprocessable();

    expect(PurchaseRequestLineStatusLabel::query()->orderBy('sort_order')->pluck('id')->all())->toBe($all);
})->with([
    'missing one' => [fn (array $all): array => array_slice($all, 1)],
    'unknown id' => [fn (array $all): array => [...array_slice($all, 1), 9999]],
    'duplicate' => [fn (array $all): array => [...$all, $all[0]]],
]);

it('AC-002: for-select lists all six rows in order with the enum value, without any permission', function () {
    Sanctum::actingAs(User::factory()->create());

    $items = collect($this->getJson('/api/purchase-request-line-statuses/for-select')->assertOk()->json('items'));

    expect($items->pluck('meta.system_key')->all())->toBe(LineStatus::values())
        ->and($items->pluck('label')->all())->toBe(['Da approvare', 'Approvato', 'Ordinato', 'Ricevuto', 'Rifiutato', 'Stand by']);
});

it('AC-006: the lines grid exposes the status badges from the catalog and follows renames and order', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    lineStatusLabel(LineStatus::OnHold)->update(['name' => 'In pausa', 'color' => 'violet', 'icon' => 'moon']);

    $badges = collect($this->getJson('/api/tables/purchase-request-lines/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'status')['badges'];

    expect(collect($badges)->pluck('value')->all())->toBe(LineStatus::values())
        ->and(collect($badges)->firstWhere('value', 'on_hold'))->toMatchArray(['label' => 'In pausa', 'color' => 'violet', 'icon' => 'moon']);
});

it('AC-006: the lines grid sorts by status following the catalog order, not the alphabet', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    purchaseRequestWithLines([LineStatus::Rejected, LineStatus::PendingApproval, LineStatus::Approved]);
    $ids = PurchaseRequestLineStatusLabel::query()->orderBy('sort_order')->pluck('id')->reverse()->values()->all();
    $statuses = fn (string $direction): array => collect($this->postJson('/api/tables/purchase-request-lines/rows', [
        'startRow' => 0, 'endRow' => 50, 'sortModel' => [['colId' => 'status', 'sort' => $direction]],
    ])->assertOk()->json('items'))->pluck('status')->all();

    expect($statuses('asc'))->toBe(['pending_approval', 'approved', 'rejected']);

    Sanctum::actingAs(lineStatusActorWith(['update']));
    $this->postJson('/api/purchase-request-line-statuses/reorder', ['ordered_ids' => $ids])->assertOk();
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));

    expect($statuses('asc'))->toBe(['rejected', 'approved', 'pending_approval']);
});
