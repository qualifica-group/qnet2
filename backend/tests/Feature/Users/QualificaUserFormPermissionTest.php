<?php

use App\Enums\ContactTypeEnum;
use App\Models\PersonalData;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue as Catalogue;
use Database\Seeders\QualificaOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// User directive 2026-09-25: whoever sees the users sees everything the user
// form holds. The form reads the card by owner and writes the contacts and
// addresses of an existing card on their own endpoints, outside `users.*`.
it('lets the supervisor load and edit the personal-data stack of the user form', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();
    $target = User::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($supervisor);

    $this->getJson("/api/personal-data?personable_type=user&personable_id={$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $card->id);

    $contactId = $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Email->value,
        'value' => 'grace@example.com',
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/contacts/{$contactId}", ['type' => ContactTypeEnum::Email->value, 'value' => 'ada@example.com'])
        ->assertOk();
    $this->deleteJson("/api/contacts/{$contactId}")->assertNoContent();

    $addressId = $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => 'Via Roma 1',
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/addresses/{$addressId}", ['line1' => 'Via Milano 2'])->assertOk();
    $this->deleteJson("/api/addresses/{$addressId}")->assertNoContent();
});

it('grants the user form stack only to the roles that see the users', function () {
    $this->seed(QualificaOperatorSeeder::class);

    foreach (Catalogue::ROLES as $name => $definition) {
        $role = Role::findByName($name);
        $seesUsers = $role->hasPermissionTo(Catalogue::USER_FORM_GATE);

        foreach (Catalogue::USER_FORM_MODULES as $resource) {
            foreach (Catalogue::USER_FORM_ABILITIES as $ability) {
                expect($role->hasPermissionTo("{$resource}.{$ability}"))->toBe($seesUsers, "{$name}: {$resource}.{$ability}");
            }

            // Bulk and audit abilities are not part of the form.
            foreach (['export', 'import', 'viewActivity'] as $ability) {
                expect($role->hasPermissionTo("{$resource}.{$ability}"))->toBeFalse("{$name}: {$resource}.{$ability}");
            }
        }
    }

    expect(Role::findByName(Catalogue::SUPERVISOR_ROLE)->hasPermissionTo(Catalogue::USER_FORM_GATE))->toBeTrue()
        ->and(Role::findByName(Catalogue::COORDINATOR_ROLE)->hasPermissionTo(Catalogue::USER_FORM_GATE))->toBeFalse();
});
