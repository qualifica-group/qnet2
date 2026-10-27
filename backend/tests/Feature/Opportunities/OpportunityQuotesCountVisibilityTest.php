<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0316, D-10 / AC-015: the detail's `quotes_count` counts only the
 * quotes the actor can see.
 */
uses(RefreshDatabase::class);

function quotesCountActor(array $extra = []): User
{
    foreach (['opportunities.view', 'opportunities.viewAll', 'quotes.viewAll', 'quotes.viewSite'] as $name) {
        Permission::findOrCreate($name);
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['opportunities.view', 'opportunities.viewAll', ...$extra]);

    return $user;
}

it('quotes_count counts only the quotes visible to the actor', function () {
    $actor = quotesCountActor();
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $actor->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk()->assertJsonPath('data.quotes_count', 2);
});

it('quotes_count counts every quote with quotes.viewAll', function () {
    $actor = quotesCountActor(['quotes.viewAll']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->count(3)->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk()->assertJsonPath('data.quotes_count', 3);
});
