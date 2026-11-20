<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0417 AC-007: a referent used as "Partner commerciale" cannot be
 * deleted, moves to the survivor on a referent merge, and the registry merge
 * offers the field.
 */
uses(RefreshDatabase::class);

it('AC-007: DELETE of a referent used as opportunity partner answers 409', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $referent = Referent::factory()->create();
    Opportunity::factory()->create(['commercial_partner_id' => $referent->id]);

    $this->deleteJson("/api/referents/{$referent->id}")->assertStatus(409);
    $this->assertDatabaseHas('referents', ['id' => $referent->id]);
});

it('AC-007: a referent merge moves the partner references to the survivor', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeReferent('Ada', 'Lovelace');
    $duplicate = mergeReferent('Ada', 'Lovelace');
    $registry = mergeCompany('Acme', ['commercial_partner_id' => $duplicate->id]);
    $opportunity = Opportunity::factory()->create(['commercial_partner_id' => $duplicate->id]);
    $quote = Quote::factory()->create(['commercial_partner_id' => $duplicate->id]);

    $this->postJson('/api/referents/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    expect($registry->fresh()->commercial_partner_id)->toBe($survivor->id)
        ->and($opportunity->fresh()->commercial_partner_id)->toBe($survivor->id)
        ->and($quote->fresh()->commercial_partner_id)->toBe($survivor->id);
});

it('AC-007: the registry merge preview offers commercial_partner_id as a relation field', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $first = mergeCompany('Acme Srl', ['commercial_partner_id' => Referent::factory()->create()->id]);
    $second = mergeCompany('Acme S.r.l.');

    $fields = collect($this->postJson('/api/registries/merge/preview', ['ids' => [$first->id, $second->id]])->assertOk()->json('data.fields'));

    expect($fields->pluck('key')->all())->toContain('commercial_partner_id');
});

it('AC-007: the registry merge keeps the survivor partner chosen per field', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $partner = Referent::factory()->create();
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.', ['commercial_partner_id' => $partner->id]);

    $this->postJson('/api/registries/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$duplicate->id],
        'fields' => ['commercial_partner_id' => $duplicate->id],
    ])->assertOk();

    expect(Registry::findOrFail($survivor->id)->commercial_partner_id)->toBe($partner->id);
});
