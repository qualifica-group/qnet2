<?php

use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `QuoteService::delete()` guard added by spec 0093 (D-5): a quote with
 * at least one WorkOrder cannot be deleted. The ONLY change spec 0093 makes
 * to the Offerte module.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteDeleteGuardUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteDeleteGuardUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

it('DELETE /api/quotes/{id}: 409 when the quote has a work order, nothing is deleted (AC-061)', function () {
    $actor = quoteDeleteGuardUserWith(['delete']);
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quotes/{$quote->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This quote has work orders and cannot be deleted.');

    $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id]);
});

it('DELETE /api/quotes/{id}: 204 when the quote has NO work order — no regression (AC-062)', function () {
    $actor = quoteDeleteGuardUserWith(['delete']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quotes/{$quote->id}")->assertNoContent();

    $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
});
