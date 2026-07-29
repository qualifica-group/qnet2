<?php

use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| CRUD — /api/quote-statuses (spec 0065, plain clone of opportunity-statuses)
|--------------------------------------------------------------------------
*/

if (! function_exists('quoteStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("quote-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quote-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/quote-statuses (AC-011)
// ---------------------------------------------------------------------------

it('create: 201 + system_key null, sort_order placed before the closed tail (AC-011)', function () {
    $actor = quoteStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses', ['name' => 'In Negoziazione', 'color' => 'blue', 'group' => 'open', 'sort_order' => 2])
        ->assertCreated()
        ->assertJsonPath('data.name', 'In Negoziazione')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.group', 'open')
        ->assertJsonPath('data.system_key', null);

    $ordered = QuoteStatus::query()->orderBy('sort_order')->pluck('system_key', 'name');

    expect($ordered->keys()->first())->toBe('Bozza')
        ->and($ordered->keys()->last())->toBe('Rifiutata')
        ->and($ordered->keys()->get($ordered->keys()->count() - 2))->toBe('Accettata');
});

it('create: 422 when name is missing', function () {
    $actor = quoteStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses', ['group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when group is missing', function () {
    $actor = quoteStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses', ['name' => 'Attesa'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('create: 422 when group is not one of open/pending/closed', function () {
    $actor = quoteStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses', ['name' => 'Attesa', 'group' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('create: 422 when name duplicates an existing status, no row created', function () {
    $actor = quoteStatusUserWith(['create']);
    QuoteStatus::factory()->create(['name' => 'Duplicata']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses', ['name' => 'Duplicata', 'group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(QuoteStatus::where('name', 'Duplicata')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// show — GET /api/quote-statuses/{quoteStatus}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = quoteStatusUserWith(['view']);
    $target = QuoteStatus::factory()->create(['name' => 'Attiva', 'color' => 'blue', 'sort_order' => 3]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/quote-statuses/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attiva')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.sort_order', 3);
});

it('show: 404 for a non-existent quote status', function () {
    $actor = quoteStatusUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/quote-statuses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/quote-statuses/{quoteStatus}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the quote status', function () {
    $actor = quoteStatusUserWith(['update']);
    $target = QuoteStatus::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-statuses/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('quote_statuses', ['id' => $target->id, 'name' => 'After']);
});

it('update: 422 when name duplicates ANOTHER existing status', function () {
    $actor = quoteStatusUserWith(['update']);
    QuoteStatus::factory()->create(['name' => 'Taken']);
    $target = QuoteStatus::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-statuses/{$target->id}", ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// 403 without the permission on EVERY verb, no write
// ---------------------------------------------------------------------------

it('GET show: 403 without quote-statuses.view', function () {
    $actor = quoteStatusUserWith([]);
    $target = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/quote-statuses/{$target->id}")->assertForbidden();
});

it('POST create: 403 without quote-statuses.create, no row created', function () {
    $actor = quoteStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses', ['name' => 'Nope', 'group' => 'open'])->assertForbidden();

    // spec 0065 (D-2): the create migration seeds the 3 mandatory system
    // rows ("Bozza"/"Accettata"/"Rifiutata") unconditionally, so the
    // post-403 baseline is 3, not 0.
    expect(QuoteStatus::count())->toBe(3);
});

it('PATCH update: 403 without quote-statuses.update, no change persisted', function () {
    $actor = quoteStatusUserWith([]);
    $target = QuoteStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('quote_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without quote-statuses.delete, record still exists', function () {
    $actor = quoteStatusUserWith([]);
    $target = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('quote_statuses', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/quote-statuses/{quoteStatus} (AC-014)
// ---------------------------------------------------------------------------

it('delete: 409 when referenced by a Quote, status AND quote still exist (AC-014)', function () {
    $actor = quoteStatusUserWith(['delete']);
    $target = QuoteStatus::factory()->create();
    $quote = Quote::factory()->create(['quote_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-statuses/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This quote status is used by a quote and cannot be deleted.');

    $this->assertDatabaseHas('quote_statuses', ['id' => $target->id]);
    $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
});

it('delete: 204 + removed when not referenced by anything', function () {
    $actor = quoteStatusUserWith(['delete']);
    $target = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-statuses/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('quote_statuses', ['id' => $target->id]);
});
