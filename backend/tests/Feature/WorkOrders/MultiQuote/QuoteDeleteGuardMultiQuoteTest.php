<?php

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function multiQuoteDeleteActor(): User
{
    foreach (['viewAny', 'view', 'delete', 'viewAll'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.delete', 'quotes.viewAll']);

    return $user;
}

it('AC-014: an offer with a line in a multi-offer commessa cannot be deleted', function () {
    [$workOrder, $quotes] = multiQuoteReadFixture();
    Sanctum::actingAs(multiQuoteDeleteActor());

    $this->deleteJson("/api/quotes/{$quotes[1]->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This quote has work orders and cannot be deleted.');

    $this->assertDatabaseHas('quotes', ['id' => $quotes[1]->id]);
    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id]);
});

it('an offer unrelated to any commessa is still deletable', function () {
    multiQuoteReadFixture();
    $free = Quote::factory()->create();
    QuoteLine::factory()->create(['quote_id' => $free->id]);
    Sanctum::actingAs(multiQuoteDeleteActor());

    $this->deleteJson("/api/quotes/{$free->id}")->assertSuccessful();

    $this->assertDatabaseMissing('quotes', ['id' => $free->id]);
});

it('a mono-offer commessa still blocks its offer', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(multiQuoteDeleteActor());

    $this->deleteJson("/api/quotes/{$workOrder->quote_id}")->assertStatus(409);
});

it('deleting an offer requires quotes.delete', function () {
    [, $quotes] = multiQuoteReadFixture();
    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson("/api/quotes/{$quotes[0]->id}")->assertForbidden();
});
