<?php

use App\Models\Invoice;
use App\Models\InvoiceTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// spec 0247, AC-007: CRUD, permissions, reorder and the delete guard of the invoice tag catalogue.

function invoiceTagActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        Permission::findOrCreate("invoice-tags.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("invoice-tags.{$ability}");
    }

    return $user;
}

it('AC-007: creates a tag at the end of the order and exposes the contract shape without icon or default', function () {
    Sanctum::actingAs(invoiceTagActorWith(['create']));
    $last = InvoiceTag::query()->max('sort_order');

    $response = $this->postJson('/api/invoice-tags', ['name' => 'Acconto', 'color' => 'blue', 'description' => 'Advance'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acconto')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data' => ['id', 'name', 'description', 'color', 'sort_order', 'is_active']]);

    expect($response->json('data.sort_order'))->toBeGreaterThan($last)
        ->and($response->json('data'))->not->toHaveKeys(['icon', 'is_default']);
});

it('AC-007: rejects a duplicate name, an unknown color, a client sort_order and an icon-less payload without name', function () {
    Sanctum::actingAs(invoiceTagActorWith(['create']));

    $this->postJson('/api/invoice-tags', ['name' => 'Preventivo', 'color' => 'blue'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/invoice-tags', ['name' => 'X', 'color' => '#ff0000'])->assertUnprocessable()->assertJsonValidationErrors('color');
    $this->postJson('/api/invoice-tags', ['name' => 'X', 'color' => 'blue', 'sort_order' => 5])->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $this->postJson('/api/invoice-tags', ['color' => 'blue'])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('AC-007: shows and updates a tag, keeping the name unique ignoring itself', function () {
    Sanctum::actingAs(invoiceTagActorWith(['view', 'update']));
    $tag = InvoiceTag::factory()->create(['name' => 'Old']);

    $this->getJson("/api/invoice-tags/{$tag->id}")->assertOk()->assertJsonPath('data.name', 'Old')->assertJsonStructure(['permissions']);
    $this->patchJson("/api/invoice-tags/{$tag->id}", ['name' => 'Old', 'color' => 'teal', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.color', 'teal')->assertJsonPath('data.is_active', false);
    $this->putJson("/api/invoice-tags/{$tag->id}", ['name' => 'Preventivo'])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('AC-007: every endpoint is 403 without its permission', function () {
    $tag = InvoiceTag::factory()->create();
    $ids = InvoiceTag::query()->orderBy('sort_order')->pluck('id')->all();

    Sanctum::actingAs(invoiceTagActorWith([]));

    $this->getJson("/api/invoice-tags/{$tag->id}")->assertForbidden();
    $this->postJson('/api/invoice-tags', ['name' => 'Z', 'color' => 'blue'])->assertForbidden();
    $this->patchJson("/api/invoice-tags/{$tag->id}", ['name' => 'Z'])->assertForbidden();
    $this->deleteJson("/api/invoice-tags/{$tag->id}")->assertForbidden();
    $this->postJson('/api/invoice-tags/reorder', ['ordered_ids' => $ids])->assertForbidden();
    expect($tag->fresh()->name)->toBe($tag->name);
});

it('AC-007: an unauthenticated call is 401 and an unknown tag is 404', function () {
    $this->getJson('/api/invoice-tags/for-select')->assertUnauthorized();

    Sanctum::actingAs(invoiceTagActorWith(['view']));
    $this->getJson('/api/invoice-tags/999999')->assertNotFound();
});

it('AC-007: deletes an unused tag and answers 409 for a tag an invoice carries', function () {
    Sanctum::actingAs(invoiceTagActorWith(['delete']));
    $unused = InvoiceTag::factory()->create();
    $used = InvoiceTag::factory()->create();
    Invoice::factory()->create(['invoice_tag_id' => $used->id]);

    $this->deleteJson("/api/invoice-tags/{$unused->id}")->assertNoContent();
    $this->deleteJson("/api/invoice-tags/{$used->id}")->assertStatus(409)->assertJsonPath('message', __('invoice_tags.in_use'));

    expect(InvoiceTag::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(InvoiceTag::query()->whereKey($used->id)->exists())->toBeTrue();
});

it('AC-007: reorder resequences every row and rejects an incomplete id list', function () {
    Sanctum::actingAs(invoiceTagActorWith(['update']));
    $extra = InvoiceTag::factory()->create();
    $ids = InvoiceTag::query()->orderBy('sort_order')->pluck('id')->all();
    $reversed = array_reverse($ids);

    $response = $this->postJson('/api/invoice-tags/reorder', ['ordered_ids' => $reversed])->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe($reversed)
        ->and(InvoiceTag::query()->orderBy('sort_order')->pluck('id')->all())->toBe($reversed)
        ->and($reversed[0])->toBe($extra->id);

    $this->postJson('/api/invoice-tags/reorder', ['ordered_ids' => [$extra->id]])->assertUnprocessable();
});

it('AC-007: for-select lists active tags by sort_order, hydrates requested ids and can include inactive ones', function () {
    Sanctum::actingAs(User::factory()->create());
    $inactive = InvoiceTag::factory()->create(['name' => 'Dormant', 'is_active' => false]);

    $names = collect($this->getJson('/api/invoice-tags/for-select')->assertOk()->json('items'))->pluck('label')->all();
    expect($names)->toBe(['Preventivo', 'Consuntivo']);

    $hydrated = $this->getJson('/api/invoice-tags/for-select?ids[]='.$inactive->id)->assertOk()->json('items');
    expect(collect($hydrated)->pluck('label')->all())->toBe(['Preventivo', 'Consuntivo', 'Dormant'])
        ->and($hydrated[2]['meta'])->toMatchArray(['is_active' => false])
        ->and($hydrated[0]['meta'])->toHaveKey('color')
        ->and($hydrated[0]['meta'])->not->toHaveKey('icon');

    $all = $this->getJson('/api/invoice-tags/for-select?include_inactive=1&search=dorm')->assertOk()->json('items');
    expect(collect($all)->pluck('label')->all())->toBe(['Dormant']);
});

it('AC-007: the table lists the tags and honours the view permission', function () {
    Sanctum::actingAs(invoiceTagActorWith(['viewAny', 'view', 'delete']));

    $rows = $this->postJson('/api/tables/invoice-tags/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items');
    expect(collect($rows)->pluck('name')->all())->toBe(['Preventivo', 'Consuntivo'])
        ->and($rows[0])->not->toHaveKeys(['icon', 'is_default'])
        ->and($rows[0]['actions'])->toBe(['view', 'delete']);

    Sanctum::actingAs(invoiceTagActorWith([]));
    $this->postJson('/api/tables/invoice-tags/rows', ['startRow' => 0, 'endRow' => 50])->assertForbidden();
});
