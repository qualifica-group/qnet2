<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/request-management/rows/{row} — the fiscal check on the
// inline `tax_code` editor. The cell PATCH carries the value ALONE, so only
// format + control character can be checked here: the consistency with the
// card's anagraphic fields needs the whole card and lives in
// ValidatesRequestClientProfile (covered by RequestManagementClientProfileTest).

uses(RefreshDatabase::class);

if (! function_exists('taxCodeInlineEditActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taxCodeInlineEditActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('taxCodeInlineEditRequest')) {
    function taxCodeInlineEditRequest(User $supervisor): Quote
    {
        $registry = Registry::factory()->withPersonalData()->create();
        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
    }
}

it('PATCH tax_code: 422 on an invalid control character, the card untouched', function () {
    $actor = taxCodeInlineEditActor(['viewAny', 'update']);
    $quote = taxCodeInlineEditRequest($actor);
    $card = $quote->opportunity->registry->personalData;
    $card->update(['type' => PersonalDataTypeEnum::Individual, 'tax_code' => 'RSSMRA80A01H501U']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'tax_code',
        'value' => 'RSSMRA80A01H501W',
    ])->assertStatus(422);

    expect($card->fresh()->tax_code)->toBe('RSSMRA80A01H501U');
});

it('PATCH tax_code: 200 on a valid personal code', function () {
    $actor = taxCodeInlineEditActor(['viewAny', 'update']);
    $quote = taxCodeInlineEditRequest($actor);
    $card = $quote->opportunity->registry->personalData;
    $card->update(['type' => PersonalDataTypeEnum::Individual]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'tax_code',
        'value' => 'RSSMRA80A01H501U',
    ])->assertOk();

    expect($card->fresh()->tax_code)->toBe('RSSMRA80A01H501U');
});

it('PATCH tax_code: 200 on a valid 11-digit entity code, the cell carrying no card type', function () {
    $actor = taxCodeInlineEditActor(['viewAny', 'update']);
    $quote = taxCodeInlineEditRequest($actor);
    $card = $quote->opportunity->registry->personalData;
    $card->update(['type' => PersonalDataTypeEnum::Company, 'company_name' => 'Acme SpA']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'tax_code',
        'value' => '00743110157',
    ])->assertOk();

    expect($card->fresh()->tax_code)->toBe('00743110157');
});
