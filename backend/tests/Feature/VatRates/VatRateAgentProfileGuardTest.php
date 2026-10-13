<?php

use App\Models\AgentFiscalProfile;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function vatRateGuardActor(): User
{
    foreach (['viewAny', 'delete'] as $ability) {
        Permission::findOrCreate("vat-rates.{$ability}");
    }

    return User::factory()->create()->givePermissionTo(['vat-rates.viewAny', 'vat-rates.delete']);
}

it('M-3: deleting a VAT rate used by an agent fiscal profile is a 409 and the rate stays', function () {
    Sanctum::actingAs(vatRateGuardActor());
    $rate = VatRate::factory()->create();
    AgentFiscalProfile::factory()->create(['vat_rate_id' => $rate->id]);

    $this->deleteJson("/api/vat-rates/{$rate->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This VAT rate is used by an agent fiscal profile.');

    $this->assertDatabaseHas('vat_rates', ['id' => $rate->id]);
});

it('M-3: bulk delete refuses the used rate and removes the free one', function () {
    Sanctum::actingAs(vatRateGuardActor());
    $used = VatRate::factory()->create();
    $free = VatRate::factory()->create();
    AgentFiscalProfile::factory()->create(['vat_rate_id' => $used->id]);

    $response = $this->postJson('/api/tables/vat-rates/bulk-delete', ['ids' => [$used->id, $free->id]])->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $used->id)['reason'])->toBe('guarded');
    $this->assertDatabaseHas('vat_rates', ['id' => $used->id]);
    $this->assertDatabaseMissing('vat_rates', ['id' => $free->id]);
});

it('M-3: an unreferenced VAT rate is still deletable', function () {
    Sanctum::actingAs(vatRateGuardActor());
    $rate = VatRate::factory()->create();

    $this->deleteJson("/api/vat-rates/{$rate->id}")->assertNoContent();
});
