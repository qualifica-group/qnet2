<?php

use App\Models\QuoteStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

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
// AC-016 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/quote-statuses/columns: 403 without viewAny, 200 with the declared columns (AC-016)', function () {
    $actor = quoteStatusUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/quote-statuses/columns')->assertForbidden();

    $actor = quoteStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/quote-statuses/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('quote-statuses')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']]);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'color', 'sort_order', 'group', 'created_at']);
});

// ---------------------------------------------------------------------------
// AC-017 — rows: no `delete` action on a system row, present on a custom row
// ---------------------------------------------------------------------------

it('rows: delete is absent on system rows and present on custom rows (AC-017)', function () {
    $actor = quoteStatusUserWith(['viewAny', 'view', 'update', 'delete']);
    QuoteStatus::factory()->create(['name' => 'Personalizzata', 'color' => 'red', 'sort_order' => 5]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quote-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $items = collect($response->json('items'));

    $customRow = $items->firstWhere('name', 'Personalizzata');
    expect($customRow)->not->toBeNull()
        ->and($customRow['actions'])->toContain('delete');

    foreach (['new', 'won', 'lost'] as $systemKey) {
        $systemRow = $items->firstWhere('system_key', $systemKey);
        expect($systemRow)->not->toBeNull()
            ->and($systemRow['actions'])->not->toContain('delete');
    }
});

it('rows: delete is absent everywhere without quote-statuses.delete (AC-017)', function () {
    $actor = quoteStatusUserWith(['viewAny', 'view', 'update']);
    QuoteStatus::factory()->create(['name' => 'Personalizzata']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quote-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    foreach ($response->json('items') as $row) {
        expect($row['actions'])->not->toContain('delete');
    }
});
