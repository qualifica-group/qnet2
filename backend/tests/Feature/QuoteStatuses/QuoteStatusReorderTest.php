<?php

use App\Models\QuoteStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/quote-statuses/reorder (spec 0065, AC-015)
|--------------------------------------------------------------------------
*/

if (! function_exists('quoteStatusReorderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteStatusReorderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("quote-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quote-statuses.{$ability}");
        }

        return $user;
    }
}

it('reorder: a valid permutation resequences the customs, Bozza stays 0, Accettata then Rifiutata stay last (AC-015)', function () {
    $actor = quoteStatusReorderUserWith(['update']);
    $first = QuoteStatus::factory()->create(['name' => 'Alpha']);
    $second = QuoteStatus::factory()->create(['name' => 'Beta']);
    $third = QuoteStatus::factory()->create(['name' => 'Gamma']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/quote-statuses/reorder', [
        'ordered_ids' => [$third->id, $first->id, $second->id],
    ])->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$third->id]['sort_order'])->toBe(10)
        ->and($rows[$first->id]['sort_order'])->toBe(20)
        ->and($rows[$second->id]['sort_order'])->toBe(30);

    $newRow = $rows->firstWhere('system_key', 'new');
    $wonRow = $rows->firstWhere('system_key', 'won');
    $lostRow = $rows->firstWhere('system_key', 'lost');
    expect($newRow['sort_order'])->toBe(0)
        ->and($wonRow['sort_order'])->toBe(40)
        ->and($lostRow['sort_order'])->toBe(50);
});

it('reorder: 422 when ordered_ids includes a system status id (AC-015)', function () {
    $actor = quoteStatusReorderUserWith(['update']);
    $newStatus = QuoteStatus::where('system_key', 'new')->firstOrFail();
    $custom = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses/reorder', ['ordered_ids' => [$newStatus->id, $custom->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids is missing a custom id (AC-015)', function () {
    $actor = quoteStatusReorderUserWith(['update']);
    QuoteStatus::factory()->create();
    $second = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses/reorder', ['ordered_ids' => [$second->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids contains a duplicate (AC-015)', function () {
    $actor = quoteStatusReorderUserWith(['update']);
    $custom = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses/reorder', ['ordered_ids' => [$custom->id, $custom->id]])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids.0');
});

it('reorder: 422 when ordered_ids includes a non-existent id (AC-015)', function () {
    $actor = quoteStatusReorderUserWith(['update']);
    $custom = QuoteStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses/reorder', ['ordered_ids' => [$custom->id, 999999]])
        ->assertStatus(422);
});

it('reorder: 403 without quote-statuses.update, order unchanged', function () {
    $actor = quoteStatusReorderUserWith([]);
    $custom = QuoteStatus::factory()->create(['sort_order' => 20]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-statuses/reorder', ['ordered_ids' => [$custom->id]])->assertForbidden();

    $this->assertDatabaseHas('quote_statuses', ['id' => $custom->id, 'sort_order' => 20]);
});
