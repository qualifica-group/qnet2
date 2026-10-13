<?php

use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0211: the Anagrafiche quick-search also matches the card's VAT number,
 * every phone of the card, and the name/phones of a linked referent — on top
 * of the unchanged "contains" match on `name`.
 */
beforeEach(function () {
    Permission::findOrCreate('registries.viewAny');
    $user = User::factory()->create();
    $user->givePermissionTo('registries.viewAny');
    Sanctum::actingAs($user);
});

/**
 * A registry whose card carries the given type/VAT number and phone contacts.
 *
 * @param  array<int, array{value: string, is_primary: bool}>  $phones
 */
function quickSearchRegistry(string $name, string $type = 'company', ?string $vatNumber = null, array $phones = []): Registry
{
    $registry = Registry::factory()->create(['name' => $name]);
    $card = $registry->personalData()->create([
        'type' => $type,
        'company_name' => $type === 'company' ? $name : null,
        'vat_number' => $vatNumber,
    ]);

    foreach ($phones as $phone) {
        $card->contacts()->create(['type' => 'phone', ...$phone]);
    }

    return $registry;
}

/**
 * A referent with its own card and phone, linked to `$registry` when given.
 */
function quickSearchReferent(string $firstName, string $lastName, string $phone, ?Registry $registry = null): Referent
{
    $referent = Referent::factory()->create(['name' => $firstName.' '.$lastName]);
    $card = $referent->personalData()->create(['type' => 'individual', 'first_name' => $firstName, 'last_name' => $lastName]);
    $card->contacts()->create(['type' => 'phone', 'value' => $phone, 'is_primary' => true]);
    $registry?->referents()->attach($referent);

    return $referent;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<int, int>
 */
function quickSearchRegistryIds(string $term, array $extra = []): array
{
    return collect(
        test()->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25, 'search' => $term, ...$extra])
            ->assertOk()
            ->json('items')
    )->pluck('id')->sort()->values()->all();
}

it('AC-001: matches the VAT number, whole or by its first digits', function (string $term) {
    $match = quickSearchRegistry('Acme Spa', vatNumber: '01234567890');
    quickSearchRegistry('Globex Srl', vatNumber: '09876543210');

    expect(quickSearchRegistryIds($term))->toBe([$match->id]);
})->with(['whole' => '01234567890', 'prefix' => '012345']);

it('AC-002: still matches the company name anywhere inside it', function () {
    $match = quickSearchRegistry('Acme Spa');
    quickSearchRegistry('Globex Srl');

    expect(quickSearchRegistryIds('cme'))->toBe([$match->id]);
});

it('AC-003: matches any phone of the card, primary or not, with or without spaces', function (string $term) {
    $match = quickSearchRegistry('Acme Spa', phones: [
        ['value' => '06 1111 2222', 'is_primary' => true],
        ['value' => '02 6885 7892', 'is_primary' => false],
    ]);
    quickSearchRegistry('Globex Srl', phones: [['value' => '02 7423 7935', 'is_primary' => true]]);

    expect(quickSearchRegistryIds($term))->toBe([$match->id]);
})->with(['spaced' => '02 6885', 'compact' => '0268857892', 'punctuated' => '(02) 6885-78']);

it('AC-004: matches the first or last name of a linked referent only', function (string $term) {
    $linked = quickSearchRegistry('Acme Spa');
    $other = quickSearchRegistry('Globex Srl');
    quickSearchReferent('Mario', 'Rossi', '333 1234567', $linked);
    quickSearchReferent('Giulia', 'Bianchi', '333 7654321');

    expect(quickSearchRegistryIds($term))->toBe([$linked->id])
        ->and(quickSearchRegistryIds('bianchi'))->toBe([])
        ->and($other->id)->not->toBeIn(quickSearchRegistryIds($term));
})->with(['first name' => 'mario', 'last name' => 'ross', 'full name' => 'mario rossi']);

it('AC-005: matches the phone of a linked referent', function () {
    $linked = quickSearchRegistry('Acme Spa');
    quickSearchRegistry('Globex Srl');
    quickSearchReferent('Mario', 'Rossi', '333 1234567', $linked);

    expect(quickSearchRegistryIds('3331234'))->toBe([$linked->id]);
});

it('AC-006: stays in AND with the column filters', function () {
    $company = quickSearchRegistry('Acme Spa', vatNumber: '01234567890');
    quickSearchRegistry('Mario Verdi', type: 'individual', vatNumber: '01234567891');

    $companiesOnly = ['filterModel' => ['registry_type' => ['filterType' => 'set', 'values' => ['company']]]];

    expect(quickSearchRegistryIds('0123456789', $companiesOnly))->toBe([$company->id]);
});

it('AC-007: a text term does not run the phone lookup', function () {
    quickSearchRegistry('Acme Spa', phones: [['value' => '333 1234567', 'is_primary' => true]]);

    DB::enableQueryLog();
    quickSearchRegistryIds('acme');
    $phoneLookups = collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'normalized_value'));

    expect($phoneLookups)->toBeEmpty();
});
