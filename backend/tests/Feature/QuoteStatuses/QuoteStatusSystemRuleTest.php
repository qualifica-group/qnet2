<?php

use App\Models\QuoteStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System-status rules for `quote-statuses` (spec 0065, D-2)
|--------------------------------------------------------------------------
|
| The 3 mandatory rows ("Bozza"/"Accettata"/"Rifiutata") are seeded
| unconditionally by the create-table migration, so every test here reads
| them back rather than creating them (system_key is UNIQUE — a second
| 'new'/'won'/'lost' row would violate it).
*/

if (! function_exists('quoteStatusSystemUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteStatusSystemUserWith(array $abilities): User
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
// AC-012 — delete guard on system rows (single), custom rows unaffected
// ---------------------------------------------------------------------------

it('delete: 422 on the system "new" row, message names it, row persists (AC-012)', function () {
    $actor = quoteStatusSystemUserWith(['delete']);
    $newStatus = QuoteStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-statuses/{$newStatus->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$newStatus->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('quote_statuses', ['id' => $newStatus->id]);
});

it('delete: 422 on the system "won" row (AC-012)', function () {
    $actor = quoteStatusSystemUserWith(['delete']);
    $wonStatus = QuoteStatus::where('system_key', 'won')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-statuses/{$wonStatus->id}")->assertStatus(422);

    $this->assertDatabaseHas('quote_statuses', ['id' => $wonStatus->id]);
});

it('delete: 422 on the system "lost" row (AC-012)', function () {
    $actor = quoteStatusSystemUserWith(['delete']);
    $lostStatus = QuoteStatus::where('system_key', 'lost')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-statuses/{$lostStatus->id}")->assertStatus(422);

    $this->assertDatabaseHas('quote_statuses', ['id' => $lostStatus->id]);
});

it('delete: a custom, unreferenced row still returns 204 (invariant)', function () {
    $actor = quoteStatusSystemUserWith(['delete']);
    $custom = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-statuses/{$custom->id}")->assertNoContent();
});

// ---------------------------------------------------------------------------
// AC-013 — update guard: name/color allowed, group rejected
// ---------------------------------------------------------------------------

it('update: 200 when a system row changes ONLY name/color (AC-013)', function () {
    $actor = quoteStatusSystemUserWith(['update']);
    $newStatus = QuoteStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-statuses/{$newStatus->id}", ['name' => 'Bozza aggiornata', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bozza aggiornata')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.system_key', 'new');

    $this->assertDatabaseHas('quote_statuses', ['id' => $newStatus->id, 'name' => 'Bozza aggiornata', 'color' => 'teal']);
});

it('update: 422 when a system row payload includes group, nothing persists (AC-013)', function () {
    $actor = quoteStatusSystemUserWith(['update']);
    $newStatus = QuoteStatus::where('system_key', 'new')->firstOrFail();
    $originalGroup = $newStatus->group->value;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-statuses/{$newStatus->id}", ['group' => 'closed_lost'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('quote_statuses', ['id' => $newStatus->id, 'group' => $originalGroup]);
});

it('update: a custom row accepts group (AC-013)', function () {
    $actor = quoteStatusSystemUserWith(['update']);
    $custom = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-statuses/{$custom->id}", ['group' => 'pending'])
        ->assertOk()
        ->assertJsonPath('data.group', 'pending');
});
