<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * GET /api/quotes/{quote}: the read-only `referent` projected from the parent
 * Opportunita' carries the referent's Tipo referente beside the name.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteDerivedReferentActor')) {
    function quoteDerivedReferentActor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('quotes.view'), Permission::findOrCreate('quotes.viewAll')]);

        return $user;
    }
}

it('show: the parent opportunity referent carries its Tipo referente', function () {
    $type = ReferentType::factory()->create(['name' => 'Contabile']);
    $referent = Referent::factory()->create(['name' => 'Ada Contact', 'referent_type_id' => $type->id]);
    $opportunity = Opportunity::factory()->create(['referent_id' => $referent->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs(quoteDerivedReferentActor());

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.referent', [
            'id' => $referent->id,
            'name' => 'Ada Contact',
            'referent_type' => ['id' => $type->id, 'name' => 'Contabile'],
        ]);
});
