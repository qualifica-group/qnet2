<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/request-management/rows/{row} — an inline cell edit stores
// the value in the SAME canonical shape the card form does (user directive
// 2026-07-23), driven by the column's `format` declaration.

uses(RefreshDatabase::class);

if (! function_exists('inlineFormattingActor')) {
    function inlineFormattingActor(): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.update']);

        return $user;
    }
}

if (! function_exists('inlineFormattingRequest')) {
    function inlineFormattingRequest(User $supervisor): Quote
    {
        $registry = Registry::factory()->withPersonalData()->create();
        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
    }
}

it('PATCH first_name: title-cases the typed value', function () {
    $actor = inlineFormattingActor();
    $quote = inlineFormattingRequest($actor);
    $card = $quote->opportunity->registry->personalData;
    $card->update(['type' => PersonalDataTypeEnum::Individual]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'first_name',
        'value' => '  anna   maria ',
    ])->assertOk();

    expect($card->fresh()->first_name)->toBe('Anna Maria');
});

it('PATCH last_name: uppercases the letter after an apostrophe', function () {
    $actor = inlineFormattingActor();
    $quote = inlineFormattingRequest($actor);
    $card = $quote->opportunity->registry->personalData;
    $card->update(['type' => PersonalDataTypeEnum::Individual]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'last_name',
        'value' => "D'ANGELO",
    ])->assertOk();

    expect($card->fresh()->last_name)->toBe("D'Angelo");
});

it('PATCH phone: keeps only the digits of the typed number', function (string $typed, string $stored) {
    $actor = inlineFormattingActor();
    $quote = inlineFormattingRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'phone',
        'value' => $typed,
    ])->assertOk();

    $phone = $quote->opportunity->registry->personalData->fresh()->contacts()->first();

    expect($phone->value)->toBe($stored);
})->with([
    ['333 12 34 567', '3331234567'],
    ['+39 333-1234567', '+393331234567'],
]);

it('PATCH tax_code: accepts a code typed lowercase and stores it uppercase', function () {
    $actor = inlineFormattingActor();
    $quote = inlineFormattingRequest($actor);
    $card = $quote->opportunity->registry->personalData;
    $card->update(['type' => PersonalDataTypeEnum::Individual]);
    Sanctum::actingAs($actor);

    // Without the pre-validation formatting the TaxCode rule would see the raw
    // string — this asserts format runs BEFORE the rules, not after.
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'tax_code',
        'value' => 'rss mra80a01h501u',
    ])->assertOk();

    expect($card->fresh()->tax_code)->toBe('RSSMRA80A01H501U');
});
