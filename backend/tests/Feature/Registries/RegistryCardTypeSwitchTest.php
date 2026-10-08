<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0200 D-4: the anagrafica detail edits its card in place and PATCHes it
 * whole, exactly as the card form buffers it — a company switched to an
 * individual still carries the old `company_name` (and SDI) in the payload.
 * The switch must save and rename the anagrafica after the person.
 */
it('switches a company card to an individual through the in-place PATCH', function (): void {
    Permission::findOrCreate('registries.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('registries.update');
    Sanctum::actingAs($actor);

    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->company()->for($registry, 'personable')->create([
        'company_name' => 'Quitzon-Heathcote',
        'sdi_code' => 'BJF0368',
    ]);

    $this->patchJson("/api/registries/{$registry->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'company_name' => 'Quitzon-Heathcote',
            'tax_code' => null,
            'vat_number' => null,
            'sdi_code' => 'BJF0368',
            'birth_date' => null,
            'birth_city_id' => null,
            'residence_city_id' => null,
            'gender' => null,
            'contacts' => [],
            'addresses' => [],
        ],
    ])->assertOk();

    expect($card->fresh()->type)->toBe(PersonalDataTypeEnum::Individual)
        ->and($registry->fresh()->name)->toBe('Mario Rossi');
});
